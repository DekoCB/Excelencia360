<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Services;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Database\Eloquent\Collection;

class IncidenciaService
{
    public function crear(
        Estudiante $estudiante,
        User $reportadoPor,
        TipoIncidenciaEnum $tipo,
        string $descripcion,
        string $fecha,
        ?Horario $horario = null,
        ?Tarea $tarea = null,
        ?Evaluacion $evaluacion = null,
    ): Incidencia {
        /** @var Incidencia $incidencia */
        $incidencia = Incidencia::query()->create([
            'estudiante_id' => $estudiante->id,
            'reportado_por' => $reportadoPor->id,
            'horario_id' => $horario?->id,
            'tarea_id' => $tarea?->id,
            'evaluacion_id' => $evaluacion?->id,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'fecha' => $fecha,
        ]);

        if ($estudiante->es_menor_edad) {
            $this->notificarApoderado($incidencia, $estudiante);
        }

        return $incidencia;
    }

    private function notificarApoderado(Incidencia $incidencia, Estudiante $estudiante): void
    {
        $telefono = $estudiante->telefonoDeContacto();

        if (! $telefono) {
            return;
        }

        $contenido = sprintf(
            'Hola, le informamos que %s registró una incidencia de tipo "%s" para %s el %s: %s',
            config('app.name'),
            $incidencia->tipo->label(),
            $estudiante->nombreCompleto(),
            $incidencia->fecha->format('d/m/Y'),
            $incidencia->descripcion,
        );

        /** @var MensajeWhatsapp $mensaje */
        $mensaje = MensajeWhatsapp::query()->create([
            'estudiante_id' => $estudiante->id,
            'telefono' => $telefono,
            'contenido' => $contenido,
            'tipo' => TipoMensajeWhatsappEnum::INCIDENCIA,
        ]);

        EnviarMensajeWhatsappJob::dispatch($mensaje);

        $incidencia->update(['notificado_apoderado_en' => now()]);
    }

    /**
     * @return Collection<int, Incidencia>
     */
    public function delEstudiante(Estudiante $estudiante): Collection
    {
        return Incidencia::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['reportadoPor', 'tarea', 'evaluacion', 'horario.curso'])
            ->latest('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Incidencia>
     */
    public function todas(): Collection
    {
        return Incidencia::query()
            ->with(['estudiante', 'reportadoPor', 'tarea', 'evaluacion', 'horario.curso'])
            ->latest('fecha')
            ->get();
    }

    /**
     * Incidencias de los estudiantes matriculados en los horarios que dicta
     * este docente (no solo las que él mismo reportó).
     *
     * @return Collection<int, Incidencia>
     */
    public function delDocente(User $docente): Collection
    {
        $estudianteIds = $this->estudiantesDelDocente($docente)->pluck('id');

        return Incidencia::query()
            ->whereIn('estudiante_id', $estudianteIds)
            ->with(['estudiante', 'reportadoPor', 'tarea', 'evaluacion', 'horario.curso'])
            ->latest('fecha')
            ->get();
    }

    /**
     * Estudiantes matriculados (aprobados) en cualquiera de los horarios
     * que dicta este docente, sin duplicados.
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesDelDocente(User $docente): Collection
    {
        $horarios = Horario::query()->where('docente_id', $docente->id)->get(['id', 'grado_id', 'ciclo_id']);

        if ($horarios->isEmpty()) {
            return new Collection;
        }

        return Estudiante::query()
            ->whereIn('id', Matricula::query()
                ->where(function ($query) use ($horarios) {
                    foreach ($horarios as $horario) {
                        $query->orWhere(fn ($query) => $query->delHorario($horario));
                    }
                })
                ->pluck('estudiante_id'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /**
     * @return Collection<int, Estudiante>
     */
    public function buscar(string $termino): Collection
    {
        return Estudiante::query()
            ->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            })
            ->limit(8)
            ->get();
    }

    /**
     * Tareas vencidas de los cursos virtuales del estudiante que aún no
     * tienen una entrega suya registrada.
     *
     * @return Collection<int, Tarea>
     */
    public function tareasNoRealizadasDe(Estudiante $estudiante): Collection
    {
        $cursoIds = CursoVirtual::query()
            ->whereIn('horario_id', $this->horariosDelEstudiante($estudiante)->pluck('id'))
            ->pluck('id');

        return Tarea::query()
            ->whereIn('curso_virtual_id', $cursoIds)
            ->where('fecha_limite', '<', now())
            ->whereDoesntHave('entregas', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->orderByDesc('fecha_limite')
            ->get();
    }

    /**
     * Evaluaciones publicadas de los horarios del estudiante que aún no
     * tienen una calificación suya registrada.
     *
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesNoRealizadasDe(Estudiante $estudiante): Collection
    {
        return Evaluacion::query()
            ->whereIn('horario_id', $this->horariosDelEstudiante($estudiante)->pluck('id'))
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->whereDoesntHave('calificaciones', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    private function horariosDelEstudiante(Estudiante $estudiante): Collection
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
            ->get();
    }
}
