<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuentaBancaria>
 */
class CuentaBancariaFactory extends Factory
{
    protected $model = CuentaBancaria::class;

    public function definition(): array
    {
        return [
            'banco' => $this->faker->randomElement(['BCP', 'BBVA', 'Interbank', 'Scotiabank']),
            'numero_cuenta' => $this->faker->numerify('###-#######-#-##'),
            'cci' => $this->faker->numerify('####################'),
            'titular' => 'CEBA E.I.R.L.',
            'activa' => true,
        ];
    }
}
