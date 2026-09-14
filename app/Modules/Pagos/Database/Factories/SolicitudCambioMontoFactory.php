<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Enums\EstadoSolicitudCambioMontoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\SolicitudCambioMonto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudCambioMonto>
 */
class SolicitudCambioMontoFactory extends Factory
{
    protected $model = SolicitudCambioMonto::class;

    public function definition(): array
    {
        return [
            'concepto_pago_id' => ConceptoPago::factory(),
            'monto_actual' => 100,
            'monto_propuesto' => 120,
            'estado' => EstadoSolicitudCambioMontoEnum::PENDIENTE,
            'fecha_solicitud' => now(),
        ];
    }
}
