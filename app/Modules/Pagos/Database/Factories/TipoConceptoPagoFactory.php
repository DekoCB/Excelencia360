<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Models\TipoConceptoPago;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoConceptoPago>
 */
class TipoConceptoPagoFactory extends Factory
{
    protected $model = TipoConceptoPago::class;

    public function definition(): array
    {
        $nombre = $this->faker->unique()->words(2, true);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => true,
        ];
    }
}
