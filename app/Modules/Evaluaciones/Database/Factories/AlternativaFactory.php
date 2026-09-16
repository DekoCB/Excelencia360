<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Evaluaciones\Models\Alternativa;
use App\Modules\Evaluaciones\Models\Pregunta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alternativa>
 */
class AlternativaFactory extends Factory
{
    protected $model = Alternativa::class;

    public function definition(): array
    {
        return [
            'pregunta_id' => Pregunta::factory(),
            'texto' => $this->faker->words(3, true),
            'es_correcta' => false,
            'orden' => 0,
        ];
    }

    public function correcta(): static
    {
        return $this->state(['es_correcta' => true]);
    }
}
