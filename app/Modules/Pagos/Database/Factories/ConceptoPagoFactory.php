<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\TipoConceptoPago;
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
            'tipo_concepto_pago_id' => fn () => $this->tipoPorSlug('mensualidad', 'Mensualidad'),
            'monto_base' => $this->faker->randomFloat(2, 50, 300),
            'activo' => true,
        ];
    }

    public function mensualidad(): static
    {
        return $this->state(fn () => ['tipo_concepto_pago_id' => $this->tipoPorSlug('mensualidad', 'Mensualidad')]);
    }

    public function certificado(): static
    {
        return $this->state(fn () => ['tipo_concepto_pago_id' => $this->tipoPorSlug('certificado', 'Certificado')]);
    }

    public function constancia(): static
    {
        return $this->state(fn () => ['tipo_concepto_pago_id' => $this->tipoPorSlug('constancia', 'Constancia')]);
    }

    public function otro(): static
    {
        return $this->state(fn () => ['tipo_concepto_pago_id' => $this->tipoPorSlug('otro', 'Otro')]);
    }

    /**
     * firstOrCreate, no factory()->create(): la migración ya siembra estos
     * 6 tipos reales -- crear uno nuevo por cada ConceptoPago chocaría con
     * el unique(slug).
     */
    private function tipoPorSlug(string $slug, string $nombre): int
    {
        return TipoConceptoPago::query()->firstOrCreate(['slug' => $slug], ['nombre' => $nombre, 'activo' => true])->id;
    }
}
