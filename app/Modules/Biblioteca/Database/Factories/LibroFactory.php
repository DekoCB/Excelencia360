<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Database\Factories;

use App\Modules\Biblioteca\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Libro>
 */
class LibroFactory extends Factory
{
    protected $model = Libro::class;

    public function definition(): array
    {
        return [
            'titulo' => ucfirst($this->faker->words(3, true)),
            'autor' => $this->faker->name(),
            'isbn' => $this->faker->unique()->isbn13(),
            'categoria' => $this->faker->randomElement(['Literatura', 'Ciencias', 'Matemática', 'Historia', 'Comunicación']),
            'editorial' => $this->faker->company(),
            'anio_publicacion' => $this->faker->numberBetween(1990, 2025),
        ];
    }
}
