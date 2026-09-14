<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Services;

use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class AsistenciaService
{
    /**
     * El autorregistro se habilita un poco antes de la hora de inicio (para
     * quien llega puntual y abre el enlace mientras espera) y se cuenta
     * como tardanza pasado este margen desde el inicio de la sesión.
     */
    private const MINUTOS_ANTES_PARA_INGRESAR = 10;

    private const MINUTOS_DE_TOLERANCIA = 15;

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelDocente(int $docenteId): Collection
    {
        return Horario::query()
            ->where('docente_id', $docenteId)
            ->with(['curso', 'grado', 'ciclo', 'dias'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelEstudiante(Estudiante $estudiante): Collection
    {
        $matriculas = $estudiante->matriculas()
            ->where('estado', 'aprobada')
            ->get(['id', 'grado_id', 'ciclo_id']);

        if ($matriculas->isEmpty()) {
            return new Collection;
        }

        return Horario::query()
            ->where(function ($query) use ($matriculas) {
                foreach ($matriculas as $matricula) {
                    $query->orWhere(fn ($query) => $query->deLaMatricula($matricula));
                }
            })
            ->with(['curso', 'grado', 'ciclo', 'docente', 'dias'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function todos(): Collection
    {
        return Horario::query()->with(['curso', 'grado', 'ciclo', 'docente', 'dias'])->get();
    }

    /**
     * Estudiantes matriculados (aprobados) en el grado y ciclo de un
     * horario -- ver Matricula::scopeDelHorario().
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesDelHorario(Horario $horario): Collection
    {
        return Estudiante::query()
            ->whereIn('id', Matricula::query()->delHorario($horario)->pluck('estudiante_id'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /**
     * @return SupportCollection<int, string>
     */
    public function fechasRegistradas(Horario $horario): SupportCollection
    {
        return Asistencia::query()
            ->where('horario_id', $horario->id)
            ->distinct()
            ->orderByDesc('fecha')
            ->pluck('fecha');
    }

    /**
     * Últimas fechas en que el horario realmente dicta clase según su
     * franja (domingo / lun_mie / mar_jue), acotadas al rango del ciclo y
     * sin pasarse de hoy — para ofrecerlas como acceso rápido al tomar
     * asistencia, en vez de fechas ya registradas que podían no coincidir
     * con el día real de clase.
     *
     * @return SupportCollection<int, string>
     */
    public function fechasDeClase(Horario $horario, int $cantidad = 8): SupportCollection
    {
        $diasValidos = $horario->dias->map(fn (HorarioDia $dia) => $dia->dia_semana->numeroCarbon())->all();

        $cursor = now()->min($horario->ciclo->fecha_fin)->copy()->startOfDay();
        $inicio = $horario->ciclo->fecha_inicio->copy()->startOfDay();

        /** @var list<string> $fechas */
        $fechas = [];

        while ($cursor->gte($inicio) && count($fechas) < $cantidad) {
            if (in_array($cursor->dayOfWeek, $diasValidos, true)) {
                $fechas[] = $cursor->format('Y-m-d');
            }

            $cursor->subDay();
        }

        return collect($fechas);
    }

    /**
     * @return Collection<int, Asistencia>
     */
    public function deSesion(Horario $horario, string $fecha): Collection
    {
        return Asistencia::query()
            ->where('horario_id', $horario->id)
            ->where('fecha', $fecha)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }

    /**
     * @param  array<int, string>  $registros  estudiante_id => EstadoAsistenciaEnum::value
     * @param  array<int, string|null>  $observaciones  estudiante_id => motivo de la justificación
     * @param  array<int, UploadedFile|null>  $justificantes  estudiante_id => documento de sustento
     */
    public function registrar(Horario $horario, string $fecha, array $registros, array $observaciones = [], array $justificantes = []): void
    {
        DB::transaction(function () use ($horario, $fecha, $registros, $observaciones, $justificantes) {
            foreach ($registros as $estudianteId => $estado) {
                $asistencia = Asistencia::query()->updateOrCreate(
                    ['horario_id' => $horario->id, 'estudiante_id' => $estudianteId, 'fecha' => $fecha],
                    ['estado' => $estado, 'observacion' => $observaciones[$estudianteId] ?? null],
                );

                if (! empty($justificantes[$estudianteId])) {
                    $asistencia->addMedia($justificantes[$estudianteId]->getRealPath())
                        ->usingFileName($justificantes[$estudianteId]->getClientOriginalName())
                        ->toMediaCollection('justificante');
                }
            }
        });
    }

    /**
     * @return array{total: int, asistio: int, porcentaje: float, por_estado: array<string, int>, registros: Collection<int, Asistencia>}
     */
    public function resumenEstudiante(Estudiante $estudiante, Horario $horario): array
    {
        $registros = Asistencia::query()
            ->where('horario_id', $horario->id)
            ->where('estudiante_id', $estudiante->id)
            ->with('solicitudJustificacion')
            ->orderByDesc('fecha')
            ->get();

        $total = $registros->count();
        $asistio = $registros->filter(fn (Asistencia $asistencia) => $asistencia->estado->cuentaComoAsistio())->count();

        $porEstado = [];
        foreach (EstadoAsistenciaEnum::cases() as $estado) {
            $porEstado[$estado->value] = $registros->where('estado', $estado)->count();
        }

        return [
            'total' => $total,
            'asistio' => $asistio,
            'porcentaje' => $total > 0 ? round(($asistio / $total) * 100, 1) : 0.0,
            'por_estado' => $porEstado,
            'registros' => $registros,
        ];
    }

    /**
     * El horario (entre los cursos del estudiante) cuya sesión está
     * ocurriendo justo ahora, o null si ninguno coincide con el día y la
     * hora actuales. Solo considera el horario en sí — no si ya se
     * autorregistró hoy, eso lo decide autorregistrar().
     */
    public function horarioEnCursoDelEstudiante(Estudiante $estudiante): ?Horario
    {
        $ahora = now();

        foreach ($this->horariosDelEstudiante($estudiante) as $horario) {
            $diaDeHoy = $horario->diaParaFecha($ahora);

            if (! $diaDeHoy) {
                continue;
            }

            $inicio = Carbon::parse($ahora->format('Y-m-d').' '.$diaDeHoy->hora_inicio)
                ->subMinutes(self::MINUTOS_ANTES_PARA_INGRESAR);
            $fin = Carbon::parse($ahora->format('Y-m-d').' '.$diaDeHoy->hora_fin);

            if ($ahora->between($inicio, $fin)) {
                return $horario;
            }
        }

        return null;
    }

    /**
     * El estudiante se marca a sí mismo en la sesión de hoy de $horario.
     * Si el docente (o Dirección) ya registró algo para esa fecha, no lo
     * pisa — el autorregistro solo llena el hueco, nunca corrige por
     * encima de lo que el staff ya decidió.
     */
    public function autorregistrar(Horario $horario, Estudiante $estudiante): Asistencia
    {
        $fecha = now()->format('Y-m-d');

        $existente = Asistencia::query()
            ->where('horario_id', $horario->id)
            ->where('estudiante_id', $estudiante->id)
            ->where('fecha', $fecha)
            ->first();

        if ($existente) {
            return $existente;
        }

        $diaDeHoy = $horario->diaParaFecha(now());
        $horaInicio = $diaDeHoy->hora_inicio ?? '00:00:00';

        $llegoTarde = now()->greaterThan(
            Carbon::parse($fecha.' '.$horaInicio)->addMinutes(self::MINUTOS_DE_TOLERANCIA)
        );

        return Asistencia::query()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $fecha,
            'estado' => $llegoTarde ? EstadoAsistenciaEnum::TARDANZA : EstadoAsistenciaEnum::PRESENTE,
        ]);
    }
}
