<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\ExamenUbicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamenUbicacion>
 */
class ExamenUbicacionFactory extends Factory
{
    protected $model = ExamenUbicacion::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'fecha' => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'costo' => $this->faker->randomFloat(2, 20, 60),
            'resultado' => $this->faker->randomElement(['Apto', 'No apto', 'Apto con observaciones']),
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }
}
