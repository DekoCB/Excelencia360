<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estudiante>
 */
class EstudianteFactory extends Factory
{
    protected $model = Estudiante::class;

    public function definition(): array
    {
        $fechaNacimiento = $this->faker->dateTimeBetween('-60 years', '-15 years')->format('Y-m-d');

        return [
            'dni' => $this->faker->unique()->numerify('########'),
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName().' '.$this->faker->lastName(),
            'fecha_nacimiento' => $fechaNacimiento,
            'es_menor_edad' => now()->diffInYears($fechaNacimiento) < 18,
            'estado_civil' => $this->faker->randomElement(EstadoCivilEnum::cases()),
            'direccion' => $this->faker->address(),
            'celular' => '9'.$this->faker->numerify('########'),
            'email' => $this->faker->safeEmail(),
            'estado' => EstadoEstudianteEnum::ACTIVO,
        ];
    }

    public function menorDeEdad(): static
    {
        return $this->state(function () {
            $fechaNacimiento = now()->subYears(15)->subDays(10)->format('Y-m-d');

            return [
                'fecha_nacimiento' => $fechaNacimiento,
                'es_menor_edad' => true,
                'estado_civil' => null,
            ];
        });
    }
}
