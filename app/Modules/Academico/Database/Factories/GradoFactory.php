<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grado>
 */
class GradoFactory extends Factory
{
    protected $model = Grado::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Grado '.$this->faker->word().' '.$this->faker->randomNumber(5),
            // orden es único en la tabla (tinyint, tope 255). Antes se usaba
            // faker->unique()->numberBetween(1, 250), pero Faker rastrea la
            // unicidad para todo el proceso PHP (no se reinicia por test), así
            // que el suite completo terminaba agotando el rango y fallaba de
            // forma intermitente según el orden de ejecución. Basarse en el
            // máximo actual evita esto: cada test arranca con la BD vacía
            // (RefreshDatabase), así que siempre da el siguiente valor libre.
            'orden' => Grado::max('orden') + 1,
            'activo' => true,
        ];
    }
}
