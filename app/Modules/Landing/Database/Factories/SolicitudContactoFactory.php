<?php

declare(strict_types=1);

namespace App\Modules\Landing\Database\Factories;

use App\Modules\Landing\Models\SolicitudContacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudContacto>
 */
class SolicitudContactoFactory extends Factory
{
    protected $model = SolicitudContacto::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'telefono' => $this->faker->numerify('9########'),
            'asunto' => 'Consulta general',
            'mensaje' => $this->faker->sentence(12),
            'atendido' => false,
        ];
    }
}
