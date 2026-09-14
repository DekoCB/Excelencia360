<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'concepto_id' => ConceptoPago::factory(),
            'cuota_id' => null,
            'monto' => $this->faker->randomFloat(2, 50, 300),
            'metodo' => MetodoPagoEnum::YAPE,
            'estado' => EstadoPagoEnum::PENDIENTE,
            'fecha_pago' => now()->format('Y-m-d'),
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoPagoEnum::APROBADO,
            'fecha_aprobacion' => now(),
        ]);
    }
}
