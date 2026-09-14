<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Database\Factories;

use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Egreso>
 */
class EgresoFactory extends Factory
{
    protected $model = Egreso::class;

    public function definition(): array
    {
        return [
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => $this->faker->sentence(),
            'monto' => $this->faker->randomFloat(2, 20, 500),
            'metodo' => MetodoPagoEnum::EFECTIVO,
            'fecha_egreso' => now(),
        ];
    }
}
