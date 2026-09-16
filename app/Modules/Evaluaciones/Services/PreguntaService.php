<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\Pregunta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreguntaService
{
    /**
     * @param  list<array{texto: string, es_correcta: bool}>  $alternativas  ignoradas si $tipo no las necesita (pregunta abierta)
     */
    public function agregar(Evaluacion $evaluacion, TipoPreguntaEnum $tipo, string $enunciado, float $puntaje, array $alternativas = []): Pregunta
    {
        $this->validarAlternativas($tipo, $alternativas);

        return DB::transaction(function () use ($evaluacion, $tipo, $enunciado, $puntaje, $alternativas) {
            $pregunta = $evaluacion->preguntas()->create([
                'tipo' => $tipo,
                'enunciado' => $enunciado,
                'puntaje' => $puntaje,
                'orden' => $evaluacion->preguntas()->count(),
            ]);

            $this->guardarAlternativas($pregunta, $tipo, $alternativas);

            return $pregunta;
        });
    }

    /**
     * @param  list<array{texto: string, es_correcta: bool}>  $alternativas
     */
    public function actualizar(Pregunta $pregunta, string $enunciado, float $puntaje, array $alternativas = []): Pregunta
    {
        $this->validarAlternativas($pregunta->tipo, $alternativas);

        DB::transaction(function () use ($pregunta, $enunciado, $puntaje, $alternativas) {
            $pregunta->update(['enunciado' => $enunciado, 'puntaje' => $puntaje]);
            // Reemplazo completo en vez de diff -- el docente reescribe la
            // lista de alternativas entera desde el formulario, así que no
            // hay un id estable por alternativa que actualizar en su lugar.
            $pregunta->alternativas()->delete();
            $this->guardarAlternativas($pregunta, $pregunta->tipo, $alternativas);
        });

        return $pregunta->refresh();
    }

    public function eliminar(Pregunta $pregunta): void
    {
        $pregunta->delete();
    }

    /**
     * @param  list<array{texto: string, es_correcta: bool}>  $alternativas
     */
    private function guardarAlternativas(Pregunta $pregunta, TipoPreguntaEnum $tipo, array $alternativas): void
    {
        if (! $tipo->requiereAlternativas()) {
            return;
        }

        foreach ($alternativas as $indice => $alternativa) {
            $pregunta->alternativas()->create([
                'texto' => $alternativa['texto'],
                'es_correcta' => $alternativa['es_correcta'],
                'orden' => $indice,
            ]);
        }
    }

    /**
     * @param  list<array{texto: string, es_correcta: bool}>  $alternativas
     */
    private function validarAlternativas(TipoPreguntaEnum $tipo, array $alternativas): void
    {
        if (! $tipo->requiereAlternativas()) {
            return;
        }

        if (count($alternativas) < 2) {
            throw ValidationException::withMessages([
                'alternativas' => 'Necesita al menos 2 alternativas.',
            ]);
        }

        $correctas = collect($alternativas)->where('es_correcta', true)->count();

        if ($correctas === 0) {
            throw ValidationException::withMessages([
                'alternativas' => 'Marca al menos una alternativa correcta.',
            ]);
        }

        if ($tipo === TipoPreguntaEnum::OPCION_UNICA && $correctas > 1) {
            throw ValidationException::withMessages([
                'alternativas' => 'Opción única solo puede tener una alternativa correcta.',
            ]);
        }
    }
}
