<?php

declare(strict_types=1);

namespace App\Modules\CajaChica\Database\Factories;

use App\Modules\CajaChica\Enums\TipoMovimientoCajaChicaEnum;
use App\Modules\CajaChica\Models\CajaChicaMovimiento;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CajaChicaMovimiento>
 */
class CajaChicaMovimientoFactory extends Factory
{
    protected $model = CajaChicaMovimiento::class;

    public function definition(): array
    {
        return [
            'tipo' => TipoMovimientoCajaChicaEnum::GASTO,
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 5, 100),
            'fecha' => now()->format('Y-m-d'),
        ];
    }

    public function asignacion(): self
    {
        return $this->state(['tipo' => TipoMovimientoCajaChicaEnum::ASIGNACION, 'categoria' => null, 'descripcion' => 'Asignación inicial del fondo']);
    }

    public function reposicion(): self
    {
        return $this->state(['tipo' => TipoMovimientoCajaChicaEnum::REPOSICION, 'categoria' => null, 'descripcion' => 'Reposición del fondo']);
    }
}
