<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluacion>
 */
class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    public function definition(): array
    {
        return [
            'horario_id' => Horario::factory(),
            'nombre' => 'Evaluación '.$this->faker->words(2, true),
            'tipo' => TipoEvaluacionEnum::FISICO,
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ];
    }

    public function publicada(): static
    {
        return $this->state(['estado' => EstadoEvaluacionEnum::PUBLICADA]);
    }

    public function virtual(): static
    {
        return $this->state(['tipo' => TipoEvaluacionEnum::VIRTUAL]);
    }

    /**
     * curso_virtual_id no se puede declarar en definition(): tiene que
     * apuntar al aula virtual del MISMO horario_id ya resuelto (sea el
     * generado por defecto o uno pasado explícitamente), no a uno
     * independiente -- por eso se deriva acá, después de que el horario
     * ya esté resuelto, en vez de con otro ::factory() suelto.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Evaluacion $evaluacion) {
            if (! array_key_exists('curso_virtual_id', $evaluacion->getAttributes())) {
                $evaluacion->curso_virtual_id = CursoVirtual::query()->firstOrCreate(
                    ['horario_id' => $evaluacion->horario_id],
                    ['activo' => true],
                )->id;
            }
        });
    }
}
