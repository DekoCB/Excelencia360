<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\IntentoEvaluacion;
use App\Modules\Evaluaciones\Models\Pregunta;
use App\Modules\Evaluaciones\Models\RespuestaEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rendir y calificar una Evaluacion de tipo Virtual: un solo intento por
 * estudiante (existir el IntentoEvaluacion ya es el candado, no hay estado
 * "borrador" -- ver enviar()), autocalificación inmediata de opción
 * múltiple/única, y calificación final recién cuando también estén
 * calificadas a mano todas las preguntas abiertas (si hay).
 */
class IntentoEvaluacionService
{
    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    public function intentoDe(Evaluacion $evaluacion, Estudiante $estudiante): ?IntentoEvaluacion
    {
        return IntentoEvaluacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();
    }

    public function puedeRendir(Evaluacion $evaluacion, Estudiante $estudiante): bool
    {
        if (! $evaluacion->esVirtual() || ! $evaluacion->estaPublicada()) {
            return false;
        }

        if ($evaluacion->disponible_hasta !== null && now()->gt($evaluacion->disponible_hasta)) {
            return false;
        }

        return $this->intentoDe($evaluacion, $estudiante) === null;
    }

    /**
     * @param  array<int, array{alternativas?: list<int>, texto?: string}>  $respuestas  pregunta_id => lo que eligió/escribió
     */
    public function enviar(Evaluacion $evaluacion, Estudiante $estudiante, array $respuestas): IntentoEvaluacion
    {
        return DB::transaction(function () use ($evaluacion, $estudiante, $respuestas) {
            $intento = IntentoEvaluacion::query()->create([
                'evaluacion_id' => $evaluacion->id,
                'estudiante_id' => $estudiante->id,
                'enviado_en' => now(),
            ]);

            foreach ($evaluacion->preguntas as $pregunta) {
                $respuesta = $respuestas[$pregunta->id] ?? [];

                if ($pregunta->tipo->esAutoCalificable()) {
                    $elegidas = array_map('intval', $respuesta['alternativas'] ?? []);
                    sort($elegidas);

                    $correctas = $pregunta->alternativas()->where('es_correcta', true)->pluck('id')->sort()->values()->all();

                    RespuestaEstudiante::query()->create([
                        'pregunta_id' => $pregunta->id,
                        'estudiante_id' => $estudiante->id,
                        'alternativas_elegidas' => $elegidas,
                        'puntaje_obtenido' => $elegidas === $correctas ? (float) $pregunta->puntaje : 0.0,
                    ]);
                } else {
                    RespuestaEstudiante::query()->create([
                        'pregunta_id' => $pregunta->id,
                        'estudiante_id' => $estudiante->id,
                        'texto_respuesta' => $respuesta['texto'] ?? null,
                    ]);
                }
            }

            $this->finalizarSiCorresponde($intento);

            return $intento->fresh();
        });
    }

    /**
     * El docente le pone puntaje a la respuesta de una pregunta abierta. Si
     * con esta ya quedaron calificadas todas las preguntas del intento, se
     * calcula y guarda la nota final (ver finalizarSiCorresponde()).
     */
    public function calificarAbierta(RespuestaEstudiante $respuesta, float $puntaje, ?int $registradoPor): void
    {
        $respuesta->update(['puntaje_obtenido' => $puntaje]);

        $intento = IntentoEvaluacion::query()
            ->where('evaluacion_id', $respuesta->pregunta->evaluacion_id)
            ->where('estudiante_id', $respuesta->estudiante_id)
            ->first();

        if ($intento && ! $intento->estaCalificado()) {
            $this->finalizarSiCorresponde($intento, $registradoPor);
        }
    }

    /**
     * La nota final se escala a 0-20 (la misma escala vigesimal que ya usa
     * Calificacion para las evaluaciones Físicas) a partir de la proporción
     * de puntaje obtenido sobre el puntaje total de la evaluación, en vez
     * de sumar puntos sueltos -- así el resto del sistema (promedios,
     * libreta, NotaLetraEnum) no necesita saber que esta nota vino de una
     * autocalificación.
     */
    private function finalizarSiCorresponde(IntentoEvaluacion $intento, ?int $registradoPor = null): void
    {
        $evaluacion = $intento->evaluacion;
        $preguntas = $evaluacion->preguntas;

        $respuestas = RespuestaEstudiante::query()
            ->where('estudiante_id', $intento->estudiante_id)
            ->whereIn('pregunta_id', $preguntas->pluck('id'))
            ->get()
            ->keyBy('pregunta_id');

        $faltaCalificar = $preguntas->contains(
            fn (Pregunta $pregunta) => $respuestas->get($pregunta->id)?->puntaje_obtenido === null
        );

        if ($faltaCalificar) {
            return;
        }

        $puntajeTotal = (float) $preguntas->sum('puntaje');
        $puntajeObtenido = (float) $respuestas->sum('puntaje_obtenido');
        $nota = $puntajeTotal > 0 ? round(($puntajeObtenido / $puntajeTotal) * 20, 2) : 0.0;

        $this->evaluaciones->calificar($evaluacion, $intento->estudiante, $nota, null, $registradoPor);

        $intento->update(['calificado_en' => now()]);
    }

    /**
     * @return Collection<int, IntentoEvaluacion>
     */
    public function resultadosDe(Evaluacion $evaluacion): Collection
    {
        return IntentoEvaluacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }
}
