<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apoderado>
 */
class ApoderadoFactory extends Factory
{
    protected $model = Apoderado::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'nombres' => $this->faker->name(),
            'dni' => $this->faker->unique()->numerify('########'),
            'celular' => '9'.$this->faker->numerify('########'),
            'correo' => $this->faker->safeEmail(),
            'direccion' => $this->faker->address(),
            'parentesco' => $this->faker->randomElement(['Madre', 'Padre', 'Tío/a', 'Abuelo/a']),
        ];
    }
}
