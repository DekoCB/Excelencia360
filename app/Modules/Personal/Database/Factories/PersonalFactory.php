<?php

declare(strict_types=1);

namespace App\Modules\Personal\Database\Factories;

use App\Modules\Personal\Models\Personal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personal>
 */
class PersonalFactory extends Factory
{
    protected $model = Personal::class;

    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'dni' => fake()->unique()->numerify('########'),
            'celular' => '9'.fake()->numerify('########'),
            'cargo' => fake()->randomElement(['Portero', 'Personal de limpieza', 'Psicóloga', 'Secretaria', 'Bibliotecario']),
            'area' => fake()->randomElement(['Administración', 'Servicios Generales', 'Bienestar Estudiantil']),
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'activo' => true,
        ];
    }
}
