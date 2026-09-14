<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\InstitucionProcedencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitucionProcedencia>
 */
class InstitucionProcedenciaFactory extends Factory
{
    protected $model = InstitucionProcedencia::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'nombre_colegio' => 'I.E. '.$this->faker->lastName(),
            'ubicacion' => $this->faker->city(),
            'anio_egreso' => $this->faker->numberBetween(1990, (int) now()->format('Y')),
        ];
    }
}
