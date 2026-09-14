<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\PlanPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPago>
 */
class PlanPagoFactory extends Factory
{
    protected $model = PlanPago::class;

    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'numero_cuotas' => 6,
            'monto_total' => 600,
        ];
    }
}
