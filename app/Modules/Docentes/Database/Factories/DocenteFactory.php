<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Database\Factories;

use App\Models\User;
use App\Modules\Docentes\Models\Docente;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Docente>
 */
class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'especialidad' => fake()->randomElement(['Matemática', 'Comunicación', 'Inglés', 'Ciencia Tecnología y Salud', 'Educación física']),
            'grado_academico' => fake()->randomElement(['Bachiller', 'Licenciado', 'Magíster']),
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Docente $docente): void {
            if (! $docente->usuario->hasRole(RolEnum::DOCENTE->value)) {
                $docente->usuario->assignRole(RolEnum::DOCENTE->value);
            }
        });
    }
}
