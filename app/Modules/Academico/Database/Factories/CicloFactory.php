<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciclo>
 */
class CicloFactory extends Factory
{
    protected $model = Ciclo::class;

    public function definition(): array
    {
        $anio = (int) $this->faker->year();
        $inicio = "{$anio}-01-01";
        $fin = "{$anio}-06-30";

        return [
            'nombre' => "{$anio}-1",
            'anio' => $anio,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => EstadoCicloEnum::PLANIFICADO,
        ];
    }

    public function activo(): static
    {
        return $this->state(['estado' => EstadoCicloEnum::ACTIVO]);
    }
}
