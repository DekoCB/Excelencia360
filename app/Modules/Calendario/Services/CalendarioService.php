<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Services;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Calendario\DTOs\ItemCalendarioData;
use App\Modules\Calendario\Enums\CategoriaCalendarioEnum;
use App\Modules\Calendario\Enums\TipoEventoEnum;
use App\Modules\Calendario\Models\EventoCalendario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Une, en una sola vista de calendario por mes, tres orígenes que hoy
 * viven en módulos distintos y no se tocan aquí: clases recurrentes
 * (Horario/HorarioDia), evaluaciones con fecha propia (Evaluacion) y
 * eventos puntuales propios de este módulo (EventoCalendario: reuniones,
 * actos, feriados). El alcance de clases/evaluaciones se calcula igual
 * que en "Mis evaluaciones"/"Mi libreta" -- reutiliza EvaluacionService
 * en vez de duplicar la lógica de qué horarios corresponden a un docente,
 * estudiante o apoderado.
 */
class CalendarioService
{
    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    public function registrarEvento(
        User $creador,
        TipoEventoEnum $tipo,
        string $titulo,
        ?string $descripcion,
        Carbon $fechaInicio,
        ?Carbon $fechaFin,
        ?string $horaInicio,
        ?string $horaFin,
    ): EventoCalendario {
        return EventoCalendario::query()->create([
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'creado_por' => $creador->id,
        ]);
    }

    public function actualizarEvento(
        EventoCalendario $evento,
        TipoEventoEnum $tipo,
        string $titulo,
        ?string $descripcion,
        Carbon $fechaInicio,
        ?Carbon $fechaFin,
        ?string $horaInicio,
        ?string $horaFin,
    ): EventoCalendario {
        $evento->update([
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ]);

        return $evento->fresh();
    }

    public function eliminarEvento(EventoCalendario $evento): void
    {
        $evento->delete();
    }

    /**
     * @return Collection<int, ItemCalendarioData>
     */
    public function itemsDelMes(User $usuario, Carbon $mes): Collection
    {
        $inicioMes = $mes->copy()->startOfMonth()->startOfDay();
        $finMes = $mes->copy()->endOfMonth()->startOfDay();

        return $this->itemsDeEventos($inicioMes, $finMes)
            ->merge($this->itemsDeClasesYEvaluaciones($usuario, $inicioMes, $finMes))
            ->sortBy([
                fn (ItemCalendarioData $item) => $item->fecha->toDateString(),
                fn (ItemCalendarioData $item) => $item->horaInicio ?? '',
            ])
            ->values();
    }

    /**
     * @return Collection<int, ItemCalendarioData>
     */
    private function itemsDeEventos(Carbon $inicioMes, Carbon $finMes): Collection
    {
        $eventos = EventoCalendario::query()
            ->where('fecha_inicio', '<=', $finMes->toDateString())
            ->whereRaw('COALESCE(fecha_fin, fecha_inicio) >= ?', [$inicioMes->toDateString()])
            ->get();

        $items = collect();

        foreach ($eventos as $evento) {
            $desde = $evento->fecha_inicio->max($inicioMes);
            $hasta = ($evento->fecha_fin ?? $evento->fecha_inicio)->min($finMes);

            for ($fecha = $desde->copy(); $fecha->lte($hasta); $fecha->addDay()) {
                $items->push(new ItemCalendarioData(
                    fecha: $fecha->copy(),
                    categoria: CategoriaCalendarioEnum::EVENTO,
                    titulo: $evento->titulo,
                    subtitulo: $evento->descripcion,
                    horaInicio: $evento->hora_inicio,
                    horaFin: $evento->hora_fin,
                    tipoEvento: $evento->tipo,
                    eventoId: $evento->id,
                ));
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, ItemCalendarioData>
     */
    private function itemsDeClasesYEvaluaciones(User $usuario, Carbon $inicioMes, Carbon $finMes): Collection
    {
        [$horarios, $soloPublicadas] = $this->horariosVisiblesPara($usuario);

        if ($horarios->isEmpty()) {
            return collect();
        }

        $items = $this->itemsDeClases($horarios, $inicioMes, $finMes);

        $evaluaciones = Evaluacion::query()
            ->whereIn('horario_id', $horarios->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->when($soloPublicadas, fn ($query) => $query->where('estado', EstadoEvaluacionEnum::PUBLICADA))
            ->with(['horario.curso', 'horario.grado'])
            ->get();

        foreach ($evaluaciones as $evaluacion) {
            $items->push(new ItemCalendarioData(
                fecha: $evaluacion->fecha->copy(),
                categoria: CategoriaCalendarioEnum::EVALUACION,
                titulo: $evaluacion->nombre,
                subtitulo: $evaluacion->horario->curso->nombre.' — '.$evaluacion->horario->grado->nombre,
            ));
        }

        return $items;
    }

    /**
     * @param  Collection<int, Horario>  $horarios
     * @return Collection<int, ItemCalendarioData>
     */
    private function itemsDeClases(Collection $horarios, Carbon $inicioMes, Carbon $finMes): Collection
    {
        $items = collect();

        for ($fecha = $inicioMes->copy(); $fecha->lte($finMes); $fecha->addDay()) {
            foreach ($horarios as $horario) {
                if (! $this->fechaDentroDelCiclo($fecha, $horario)) {
                    continue;
                }

                foreach ($horario->dias as $dia) {
                    if ($dia->dia_semana->numeroCarbon() !== $fecha->dayOfWeek) {
                        continue;
                    }

                    $items->push(new ItemCalendarioData(
                        fecha: $fecha->copy(),
                        categoria: CategoriaCalendarioEnum::CLASE,
                        titulo: $horario->curso->nombre.' — '.$horario->grado->nombre,
                        subtitulo: 'Docente: '.$horario->docente->name,
                        horaInicio: $dia->hora_inicio,
                        horaFin: $dia->hora_fin,
                    ));
                }
            }
        }

        return $items;
    }

    private function fechaDentroDelCiclo(Carbon $fecha, Horario $horario): bool
    {
        $ciclo = $horario->ciclo;

        if ($ciclo === null) {
            return true;
        }

        return $fecha->between($ciclo->fecha_inicio, $ciclo->fecha_fin);
    }

    /**
     * @return array{0: Collection<int, Horario>, 1: bool} el segundo
     *                                                     elemento indica si además hay que filtrar evaluaciones a
     *                                                     solo las publicadas (estudiante/apoderado, igual que en
     *                                                     "Mis evaluaciones").
     */
    private function horariosVisiblesPara(User $usuario): array
    {
        if ($usuario->hasPermissionTo('academico.ver')) {
            return [$this->evaluaciones->todos(), false];
        }

        if ($usuario->hasRole('docente')) {
            return [$this->evaluaciones->horariosDelDocente($usuario->id), false];
        }

        if ($usuario->hasRole('estudiante') && $usuario->estudiante !== null) {
            return [$this->evaluaciones->horariosDelEstudiante($usuario->estudiante), true];
        }

        if ($usuario->hasRole('apoderado')) {
            $estudiantes = $usuario->apoderados()->with('estudiante')->get()
                ->pluck('estudiante')
                ->filter()
                ->unique('id');

            $horarios = $estudiantes
                ->flatMap(fn (Estudiante $estudiante) => $this->evaluaciones->horariosDelEstudiante($estudiante))
                ->unique('id')
                ->values();

            return [$horarios, true];
        }

        return [collect(), false];
    }
}
