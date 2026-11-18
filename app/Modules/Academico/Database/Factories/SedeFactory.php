<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    protected $model = Sede::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Sede '.$this->faker->unique()->city(),
            'direccion' => $this->faker->streetAddress(),
            'activa' => true,
        ];
    }
}
