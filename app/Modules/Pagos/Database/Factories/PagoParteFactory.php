<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PagoParte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoParte>
 */
class PagoParteFactory extends Factory
{
    protected $model = PagoParte::class;

    public function definition(): array
    {
        return [
            'pago_id' => Pago::factory(),
            'monto' => $this->faker->randomFloat(2, 20, 200),
            'metodo' => MetodoPagoEnum::YAPE,
        ];
    }
}
