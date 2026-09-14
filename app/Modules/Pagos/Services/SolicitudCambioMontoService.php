<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Enums\EstadoSolicitudCambioMontoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\SolicitudCambioMonto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Candado de seguridad para los montos de los conceptos de pago (matrícula,
 * mensualidad, etc.): cambiarlos no los aplica de inmediato, queda como una
 * solicitud pendiente hasta que dirección la apruebe o la rechace.
 */
class SolicitudCambioMontoService
{
    public function solicitar(ConceptoPago $concepto, float $montoPropuesto, ?int $solicitadoPor): SolicitudCambioMonto
    {
        if ($concepto->solicitudesCambioMonto()->where('estado', EstadoSolicitudCambioMontoEnum::PENDIENTE)->exists()) {
            throw ValidationException::withMessages([
                'montoBase' => 'Ya hay una solicitud de cambio de monto pendiente de aprobación para este concepto.',
            ]);
        }

        return SolicitudCambioMonto::query()->create([
            'concepto_pago_id' => $concepto->id,
            'monto_actual' => $concepto->monto_base,
            'monto_propuesto' => $montoPropuesto,
            'estado' => EstadoSolicitudCambioMontoEnum::PENDIENTE,
            'solicitado_por' => $solicitadoPor,
            'fecha_solicitud' => now(),
        ]);
    }

    public function aprobar(SolicitudCambioMonto $solicitud, int $aprobadoPor): SolicitudCambioMonto
    {
        $this->validarPendiente($solicitud);

        DB::transaction(function () use ($solicitud, $aprobadoPor) {
            $solicitud->concepto->update(['monto_base' => $solicitud->monto_propuesto]);

            $solicitud->update([
                'estado' => EstadoSolicitudCambioMontoEnum::APROBADA,
                'aprobado_por' => $aprobadoPor,
                'fecha_resolucion' => now(),
            ]);
        });

        return $solicitud->refresh();
    }

    public function rechazar(SolicitudCambioMonto $solicitud, int $aprobadoPor, string $motivo): SolicitudCambioMonto
    {
        $this->validarPendiente($solicitud);

        $solicitud->update([
            'estado' => EstadoSolicitudCambioMontoEnum::RECHAZADA,
            'aprobado_por' => $aprobadoPor,
            'fecha_resolucion' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        return $solicitud;
    }

    /**
     * @return Collection<int, SolicitudCambioMonto>
     */
    public function pendientes(): Collection
    {
        return SolicitudCambioMonto::query()
            ->where('estado', EstadoSolicitudCambioMontoEnum::PENDIENTE)
            ->with(['concepto', 'solicitadoPor'])
            ->oldest('fecha_solicitud')
            ->get();
    }

    private function validarPendiente(SolicitudCambioMonto $solicitud): void
    {
        if ($solicitud->estado !== EstadoSolicitudCambioMontoEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => 'Esta solicitud ya fue procesada y no puede modificarse.',
            ]);
        }
    }
}
