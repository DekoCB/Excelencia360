<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cuota>
 */
class CuotaFactory extends Factory
{
    protected $model = Cuota::class;

    public function definition(): array
    {
        return [
            'plan_pago_id' => PlanPago::factory(),
            'numero' => 1,
            'monto' => 100,
            'fecha_vencimiento' => now()->addMonth()->format('Y-m-d'),
            'estado' => EstadoCuotaEnum::PENDIENTE,
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn () => [
            'fecha_vencimiento' => now()->subMonths(2)->format('Y-m-d'),
        ]);
    }

    public function pagada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoCuotaEnum::PAGADO,
        ]);
    }
}
