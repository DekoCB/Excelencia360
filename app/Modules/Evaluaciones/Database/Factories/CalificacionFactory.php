<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calificacion>
 */
class CalificacionFactory extends Factory
{
    protected $model = Calificacion::class;

    public function definition(): array
    {
        return [
            'evaluacion_id' => Evaluacion::factory(),
            'estudiante_id' => Estudiante::factory(),
            'nota_numerica' => $this->faker->randomFloat(2, 0, 20),
            'observaciones' => null,
            'registrado_por' => null,
        ];
    }
}
