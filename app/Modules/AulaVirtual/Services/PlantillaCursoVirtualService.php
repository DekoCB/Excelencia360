<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\PlantillaCursoVirtual;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guarda y aplica plantillas de aula virtual: una copia reutilizable del
 * contenido de un curso (materiales, clases grabadas, tareas y foros, con
 * sus archivos) para no reconstruirlo desde cero en cada ciclo nuevo.
 */
class PlantillaCursoVirtualService
{
    /**
     * @return Collection<int, PlantillaCursoVirtual>
     */
    public function listarPorCurso(Curso $curso): Collection
    {
        return PlantillaCursoVirtual::query()
            ->where('curso_id', $curso->id)
            ->with('creador')
            ->latest()
            ->get();
    }

    public function guardarDesdeCursoVirtual(CursoVirtual $cursoVirtual, string $nombre, User $autor): PlantillaCursoVirtual
    {
        return DB::transaction(function () use ($cursoVirtual, $nombre, $autor) {
            $plantilla = PlantillaCursoVirtual::query()->create([
                'curso_id' => $cursoVirtual->horario->curso_id,
                'creado_por' => $autor->id,
                'nombre' => $nombre,
            ]);

            foreach ($cursoVirtual->materiales as $material) {
                $item = $plantilla->materiales()->create([
                    'semana' => $material->semana,
                    'tipo' => $material->tipo->value,
                    'titulo' => $material->titulo,
                    'url' => $material->url,
                    'orden' => $material->orden,
                ]);

                $material->getFirstMedia('archivo')?->copy($item, 'archivo');
            }

            foreach ($cursoVirtual->clasesGrabadas as $claseGrabada) {
                $item = $plantilla->clasesGrabadas()->create([
                    'semana' => $claseGrabada->semana,
                    'tipo' => $claseGrabada->tipo->value,
                    'titulo' => $claseGrabada->titulo,
                    'url' => $claseGrabada->url,
                    'orden' => $claseGrabada->orden,
                ]);

                $claseGrabada->getFirstMedia('video')?->copy($item, 'video');
            }

            foreach ($cursoVirtual->tareas as $tarea) {
                // Sin fecha_limite: es específica del ciclo de origen, no
                // tiene sentido reutilizarla. aplicar() la recalcula según
                // el ciclo destino.
                $plantilla->tareas()->create([
                    'semana' => $tarea->semana,
                    'titulo' => $tarea->titulo,
                    'descripcion' => $tarea->descripcion,
                    'puntaje_max' => $tarea->puntaje_max,
                ]);
            }

            foreach ($cursoVirtual->foros as $foro) {
                $plantilla->foros()->create([
                    'semana' => $foro->semana,
                    'titulo' => $foro->titulo,
                    'descripcion' => $foro->descripcion,
                ]);
            }

            return $plantilla;
        });
    }

    /**
     * Agrega el contenido de la plantilla al curso virtual destino (no
     * borra ni reemplaza lo que ya tuviera). La fecha límite de cada tarea
     * se recalcula a partir del inicio del ciclo destino y su semana, ya
     * que la fecha original pertenece a otro ciclo.
     */
    public function aplicar(PlantillaCursoVirtual $plantilla, CursoVirtual $cursoVirtual, User $autor): int
    {
        return DB::transaction(function () use ($plantilla, $cursoVirtual, $autor) {
            $aplicados = 0;
            $inicioCiclo = $cursoVirtual->horario->ciclo->fecha_inicio;

            foreach ($plantilla->materiales as $plantillaMaterial) {
                $material = $cursoVirtual->materiales()->create([
                    'semana' => $plantillaMaterial->semana,
                    'tipo' => $plantillaMaterial->tipo->value,
                    'titulo' => $plantillaMaterial->titulo,
                    'url' => $plantillaMaterial->url,
                    'orden' => $plantillaMaterial->orden,
                ]);

                $plantillaMaterial->getFirstMedia('archivo')?->copy($material, 'archivo');
                $aplicados++;
            }

            foreach ($plantilla->clasesGrabadas as $plantillaClase) {
                $claseGrabada = $cursoVirtual->clasesGrabadas()->create([
                    'semana' => $plantillaClase->semana,
                    'tipo' => $plantillaClase->tipo->value,
                    'titulo' => $plantillaClase->titulo,
                    'url' => $plantillaClase->url,
                    'orden' => $plantillaClase->orden,
                ]);

                $plantillaClase->getFirstMedia('video')?->copy($claseGrabada, 'video');
                $aplicados++;
            }

            foreach ($plantilla->tareas as $plantillaTarea) {
                $cursoVirtual->tareas()->create([
                    'semana' => $plantillaTarea->semana,
                    'titulo' => $plantillaTarea->titulo,
                    'descripcion' => $plantillaTarea->descripcion,
                    'puntaje_max' => $plantillaTarea->puntaje_max,
                    'fecha_limite' => $inicioCiclo->copy()->addWeeks($plantillaTarea->semana ?? 0)->setTime(23, 59),
                ]);
                $aplicados++;
            }

            foreach ($plantilla->foros as $plantillaForo) {
                $cursoVirtual->foros()->create([
                    'semana' => $plantillaForo->semana,
                    'autor_id' => $autor->id,
                    'titulo' => $plantillaForo->titulo,
                    'descripcion' => $plantillaForo->descripcion,
                ]);
                $aplicados++;
            }

            return $aplicados;
        });
    }

    public function eliminar(PlantillaCursoVirtual $plantilla): void
    {
        $plantilla->delete();
    }
}
