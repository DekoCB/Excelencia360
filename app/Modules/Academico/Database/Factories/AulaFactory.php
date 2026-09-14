<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Aula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aula>
 */
class AulaFactory extends Factory
{
    protected $model = Aula::class;

    public function definition(): array
    {
        return [
            // Antes usaba faker->unique()->numberBetween(1, 20): un rango tan
            // chico se agota rápido, porque Faker rastrea la unicidad para
            // todo el proceso PHP (no se reinicia por test) -- mismo problema
            // que tenía GradoFactory::orden, ver el comentario ahí.
            'nombre' => 'Aula '.(Aula::count() + 1),
            'capacidad' => $this->faker->numberBetween(20, 40),
            'ubicacion' => $this->faker->randomElement(['Piso 1', 'Piso 2', 'Piso 3']),
            'activa' => true,
        ];
    }
}
