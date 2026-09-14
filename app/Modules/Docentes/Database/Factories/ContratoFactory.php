<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Database\Factories;

use App\Modules\Docentes\Models\Contrato;
use App\Modules\Docentes\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        return [
            'docente_id' => Docente::factory(),
            'tipo' => fake()->randomElement(['Plazo fijo', 'Plazo indeterminado', 'Locación de servicios']),
            'fecha_inicio' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'fecha_fin' => null,
            'monto' => fake()->randomFloat(2, 1200, 3500),
            'observaciones' => null,
        ];
    }
}
