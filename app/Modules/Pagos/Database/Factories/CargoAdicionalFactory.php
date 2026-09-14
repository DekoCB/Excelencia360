<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargoAdicional>
 */
class CargoAdicionalFactory extends Factory
{
    protected $model = CargoAdicional::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'concepto' => 'Convalidación',
            'monto' => 100,
            'estado' => EstadoCuotaEnum::PENDIENTE,
        ];
    }

    public function pagado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoCuotaEnum::PAGADO,
        ]);
    }
}
