<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptoPago>
 */
class ConceptoPagoFactory extends Factory
{
    protected $model = ConceptoPago::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->words(2, true),
            'tipo' => TipoConceptoEnum::MENSUALIDAD,
            'monto_base' => $this->faker->randomFloat(2, 50, 300),
            'activo' => true,
        ];
    }
}
