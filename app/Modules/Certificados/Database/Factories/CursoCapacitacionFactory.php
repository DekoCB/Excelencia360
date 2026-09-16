<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Database\Factories;

use App\Modules\Certificados\Models\CursoCapacitacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoCapacitacion>
 */
class CursoCapacitacionFactory extends Factory
{
    protected $model = CursoCapacitacion::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Ofimática Nivel '.$this->faker->randomElement(['Básico', 'Intermedio', 'Avanzado']),
            'horas_lectivas' => $this->faker->randomElement([80, 100, 130, 150]),
            'documento_autorizacion' => 'R.D.R. N.°'.$this->faker->numberBetween(1000, 9999).'-'.now()->format('Y').'-DREP',
        ];
    }
}
