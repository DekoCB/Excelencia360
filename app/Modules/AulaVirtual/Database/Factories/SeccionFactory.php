<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seccion>
 */
class SeccionFactory extends Factory
{
    protected $model = Seccion::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'nombre' => $this->faker->sentence(2),
            'fecha' => null,
            'orden' => 0,
        ];
    }

    public function conFecha(): self
    {
        return $this->state(['nombre' => null, 'fecha' => $this->faker->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d')]);
    }
}
