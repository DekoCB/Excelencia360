<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
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
            'nombre' => "Grupo 1 - {$anio}",
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => $anio,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => EstadoCicloEnum::PLANIFICADO,
        ];
    }

    public function grupo3(): static
    {
        return $this->state(function (array $attributes) {
            $anio = $attributes['anio'];

            return [
                'nombre' => "Grupo 3 - {$anio}",
                'tipo' => TipoCicloEnum::GRUPO_3,
                'fecha_inicio' => "{$anio}-07-01",
                'fecha_fin' => "{$anio}-12-31",
            ];
        });
    }

    public function activo(): static
    {
        return $this->state(['estado' => EstadoCicloEnum::ACTIVO]);
    }

    public function anual(): static
    {
        return $this->state(function (array $attributes) {
            $anio = $attributes['anio'];

            return [
                'nombre' => "Periodo Académico Anual - {$anio}",
                'modalidad' => ModalidadCicloEnum::ANUAL,
                'tipo' => null,
                'fecha_inicio' => "{$anio}-03-01",
                'fecha_fin' => "{$anio}-10-31",
            ];
        });
    }
}
