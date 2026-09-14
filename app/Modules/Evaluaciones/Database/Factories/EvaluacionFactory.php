<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
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
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ];
    }

    public function publicada(): static
    {
        return $this->state(['estado' => EstadoEvaluacionEnum::PUBLICADA]);
    }
}
