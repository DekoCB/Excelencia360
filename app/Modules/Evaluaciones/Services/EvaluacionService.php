<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Services\NotificacionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;
use Throwable;

class EvaluacionService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
    ) {}

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

    public function crear(Horario $horario, string $nombre, string $fecha, ?string $enlaceExterno = null, ?string $disponibleHasta = null, ?int $semana = null): Evaluacion
    {
        return Evaluacion::query()->create([
            'horario_id' => $horario->id,
            'semana' => $semana,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'enlace_externo' => $enlaceExterno,
            'disponible_hasta' => $disponibleHasta,
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ]);
    }

    public function actualizarEnlace(Evaluacion $evaluacion, ?string $enlaceExterno, ?string $disponibleHasta = null): Evaluacion
    {
        $evaluacion->update([
            'enlace_externo' => $enlaceExterno,
            'disponible_hasta' => $disponibleHasta,
        ]);

        return $evaluacion;
    }

    public function actualizarSemana(Evaluacion $evaluacion, ?int $semana): Evaluacion
    {
        $evaluacion->update(['semana' => $semana]);

        return $evaluacion;
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesPublicadasDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Calificacion>
     */
    public function calificacionesDe(Evaluacion $evaluacion): Collection
    {
        return Calificacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }

    public function calificar(Evaluacion $evaluacion, Estudiante $estudiante, float $nota, ?string $observaciones, ?int $registradoPor): Calificacion
    {
        return Calificacion::query()->updateOrCreate(
            ['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id],
            ['nota_numerica' => $nota, 'observaciones' => $observaciones, 'registrado_por' => $registradoPor],
        );
    }

    /**
     * Importa notas desde las filas de un CSV/Excel -- típicamente
     * exportado de un Google Forms al que se le agregó una pregunta de
     * DNI, ya que el correo que exporta el Form por defecto no basta para
     * identificar sin ambigüedad al estudiante. Cada fila debe traer "dni"
     * y "nota" (0 a 20), y opcionalmente "observaciones". Solo califica a
     * estudiantes matriculados en el horario de la evaluación; cada fila
     * se procesa de forma independiente, así que una fila inválida no
     * afecta a las demás (mismo patrón que
     * MatriculaService::matricularDesdeFilas()).
     *
     * @param  SupportCollection<int, SupportCollection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function calificarDesdeFilas(Evaluacion $evaluacion, SupportCollection $filas, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        $estudiantesPorDni = $this->estudiantesDelHorario($evaluacion->horario)->keyBy('dni');

        foreach ($filas as $indice => $fila) {
            try {
                $dni = trim((string) ($fila->get('dni') ?? ''));

                if ($dni === '') {
                    throw new InvalidArgumentException('La columna «dni» es obligatoria.');
                }

                $estudiante = $estudiantesPorDni->get($dni);

                if (! $estudiante) {
                    throw new InvalidArgumentException("No hay ningún estudiante matriculado en este horario con el DNI {$dni}.");
                }

                $notaValor = $fila->get('nota');

                if ($notaValor === null || trim((string) $notaValor) === '') {
                    throw new InvalidArgumentException('La columna «nota» es obligatoria.');
                }

                if (! is_numeric($notaValor)) {
                    throw new InvalidArgumentException("La nota «{$notaValor}» no es un número válido.");
                }

                $nota = (float) $notaValor;

                if ($nota < 0 || $nota > 20) {
                    throw new InvalidArgumentException('La nota debe estar entre 0 y 20.');
                }

                $observacionesValor = $fila->get('observaciones');
                $observaciones = $observacionesValor !== null && trim((string) $observacionesValor) !== ''
                    ? trim((string) $observacionesValor)
                    : null;

                $this->calificar($evaluacion, $estudiante, $nota, $observaciones, $registradoPor);

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $e->getMessage()];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    public function publicar(Evaluacion $evaluacion): void
    {
        $evaluacion->update(['estado' => EstadoEvaluacionEnum::PUBLICADA]);

        $usuarios = $this->estudiantesDelHorario($evaluacion->horario)
            ->map(fn (Estudiante $estudiante) => $estudiante->user)
            ->filter();

        $this->notificaciones->notificarVarios(
            $usuarios,
            TipoNotificacionEnum::EVALUACION_PUBLICADA,
            "Se publicó la evaluación \"{$evaluacion->nombre}\"",
            route('evaluaciones.show', $evaluacion->horario),
        );
    }

    /**
     * Evaluaciones de un horario con un enlace externo que el estudiante ya
     * puede resolver: deben estar publicadas y, si el docente puso una
     * fecha límite, todavía no haber pasado (ver Evaluacion::enlaceDisponible()).
     *
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesConEnlaceDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->whereNotNull('enlace_externo')
            ->where(function ($query) {
                $query->whereNull('disponible_hasta')->orWhere('disponible_hasta', '>=', now());
            })
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * Calificaciones del estudiante en evaluaciones ya publicadas de un horario.
     *
     * @return Collection<int, Calificacion>
     */
    public function misCalificaciones(Estudiante $estudiante, Horario $horario): Collection
    {
        return Calificacion::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereHas('evaluacion', function ($query) use ($horario) {
                $query->where('horario_id', $horario->id)->where('estado', EstadoEvaluacionEnum::PUBLICADA);
            })
            ->with('evaluacion')
            ->get();
    }

    /**
     * Promedio de las calificaciones publicadas del estudiante en un
     * horario, considerando solo los últimos N exámenes mensuales por
     * fecha -- 6 para un Grupo de 6 meses, 8 para SIAGIE anual (ver
     * Ciclo::siagie()). Si hay menos de N registrados, promedia los que
     * existan. La ponderación por tipo de evaluación queda fuera de
     * alcance: hoy solo existe un tipo (mensual).
     */
    public function promedioDelEstudiante(Estudiante $estudiante, Horario $horario): ?float
    {
        $examenesQueCuentan = $horario->ciclo->siagie?->tipo === TipoSiagieEnum::ANUAL ? 8 : 6;

        $notas = $this->misCalificaciones($estudiante, $horario)
            ->sortByDesc(fn (Calificacion $calificacion) => $calificacion->evaluacion->fecha)
            ->take($examenesQueCuentan)
            ->pluck('nota_numerica');

        if ($notas->isEmpty()) {
            return null;
        }

        return round((float) $notas->avg(), 2);
    }

    /**
     * El desglose mes a mes del promedio del estudiante en un horario, para
     * la libreta de notas: cada elemento trae "mes" (label legible, ej.
     * "Marzo 2026") y "promedio", ordenados cronológicamente. Se agrupa por
     * la fecha de la evaluación (no de la calificación, que no tiene fecha
     * propia), así que un mes sin evaluaciones calificadas simplemente no
     * aparece en el desglose.
     *
     * @return SupportCollection<int, array{mes: string, promedio: float}>
     */
    public function promedioMensualDelEstudiante(Estudiante $estudiante, Horario $horario): SupportCollection
    {
        return $this->misCalificaciones($estudiante, $horario)
            ->groupBy(fn (Calificacion $calificacion) => $calificacion->evaluacion->fecha->format('Y-m'))
            ->sortKeys()
            ->map(fn (Collection $calificaciones, string $clave) => [
                'mes' => Carbon::createFromFormat('Y-m', $clave)->translatedFormat('F Y'),
                'promedio' => round((float) $calificaciones->pluck('nota_numerica')->avg(), 2),
            ])
            ->values();
    }

    /**
     * Notas del estudiante en todos sus cursos matriculados (de cualquier
     * ciclo), usada por la sección "Mis evaluaciones" del dashboard — sin
     * tener que entrar horario por horario a revisar cada uno.
     *
     * Cada elemento es un array con las claves "horario" (Horario),
     * "calificaciones" (Collection<int, Calificacion>) y "promedio" (?float).
     * Sin generics en el @return: el TValue de Collection no es covariante
     * (ver https://phpstan.org/blog/whats-up-with-template-covariant), así
     * que ninguna anotación de forma de array sobrevive a un ->map().
     */
    public function resumenDelEstudiante(Estudiante $estudiante): SupportCollection
    {
        return collect($this->horariosDelEstudiante($estudiante))->map(fn (Horario $horario) => [
            'horario' => $horario,
            'calificaciones' => $this->misCalificaciones($estudiante, $horario),
            'promedio' => $this->promedioDelEstudiante($estudiante, $horario),
        ]);
    }

    /**
     * El resumen anterior, agrupado por ciclo (más reciente primero) con el
     * promedio general de cada uno — usado por la sección "Mis evaluaciones"
     * del dashboard del estudiante.
     *
     * Cada elemento es un array con las claves "ciclo" (Ciclo), "cursos"
     * (la Collection que devuelve resumenDelEstudiante(), ya filtrada a ese
     * ciclo) y "promedioGeneral" (?float).
     */
    public function resumenDelEstudiantePorCiclo(Estudiante $estudiante): SupportCollection
    {
        return $this->resumenDelEstudiante($estudiante)
            ->groupBy(fn (array $item) => $item['horario']->ciclo->id)
            ->map(function ($cursos) {
                $promedios = $cursos->pluck('promedio')->filter();

                return [
                    'ciclo' => $cursos->first()['horario']->ciclo,
                    'cursos' => $cursos,
                    'promedioGeneral' => $promedios->isNotEmpty() ? round((float) $promedios->avg(), 2) : null,
                ];
            })
            ->sortByDesc(fn (array $grupo) => $grupo['ciclo']->id)
            ->values();
    }
}
