<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Siagie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siagie>
 */
class SiagieFactory extends Factory
{
    protected $model = Siagie::class;

    public function definition(): array
    {
        return [
            'tipo' => TipoSiagieEnum::PRIMERO,
            'anio' => (int) $this->faker->year(),
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'estado' => EstadoCicloEnum::ACTIVO,
        ];
    }

    public function anual(): static
    {
        return $this->state(function (array $attributes) {
            $anio = $attributes['anio'];

            return [
                'tipo' => TipoSiagieEnum::ANUAL,
                'fecha_inicio' => "{$anio}-03-01",
                'fecha_fin' => "{$anio}-10-31",
            ];
        });
    }
}
