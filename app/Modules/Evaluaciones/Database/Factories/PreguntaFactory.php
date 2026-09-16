<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\Pregunta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pregunta>
 */
class PreguntaFactory extends Factory
{
    protected $model = Pregunta::class;

    public function definition(): array
    {
        return [
            'evaluacion_id' => Evaluacion::factory()->virtual(),
            'tipo' => TipoPreguntaEnum::OPCION_UNICA,
            'enunciado' => $this->faker->sentence().'?',
            'puntaje' => 4,
            'orden' => 0,
        ];
    }

    public function abierta(): static
    {
        return $this->state(['tipo' => TipoPreguntaEnum::PREGUNTA_ABIERTA]);
    }

    public function opcionMultiple(): static
    {
        return $this->state(['tipo' => TipoPreguntaEnum::OPCION_MULTIPLE]);
    }
}
