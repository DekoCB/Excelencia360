<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Database\Factories;

use App\Models\User;
use App\Modules\FlujoCaja\Models\TurnoCaja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnoCaja>
 */
class TurnoCajaFactory extends Factory
{
    protected $model = TurnoCaja::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'monto_inicial' => $this->faker->randomFloat(2, 50, 200),
            'hora_cierre' => null,
            'monto_final_declarado' => null,
            'estado' => 'abierto',
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => [
            'hora_cierre' => now(),
            'monto_final_declarado' => $this->faker->randomFloat(2, 50, 500),
            'estado' => 'cerrado',
        ]);
    }
}
