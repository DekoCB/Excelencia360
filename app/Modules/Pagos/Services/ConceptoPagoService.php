<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use Illuminate\Database\Eloquent\Collection;

class ConceptoPagoService
{
    public function __construct(
        private readonly SolicitudCambioMontoService $solicitudesCambioMonto,
    ) {}

    /**
     * @return Collection<int, ConceptoPago>
     */
    public function listar(): Collection
    {
        return ConceptoPago::query()->orderBy('nombre')->get();
    }

    /**
     * @return Collection<int, ConceptoPago>
     */
    public function activos(): Collection
    {
        return ConceptoPago::query()->where('activo', true)->orderBy('nombre')->get();
    }

    public function crear(string $nombre, TipoConceptoEnum $tipo, float $montoBase): ConceptoPago
    {
        return ConceptoPago::query()->create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'monto_base' => $montoBase,
            'activo' => true,
        ]);
    }

    /**
     * El nombre, tipo y estado activo se aplican de inmediato. El monto no:
     * cambiarlo solo genera una solicitud pendiente de aprobación de
     * dirección (ver {@see SolicitudCambioMontoService}), para que nadie
     * pueda alterar precios de matrícula/pensión sin ese control.
     */
    public function actualizar(ConceptoPago $concepto, string $nombre, TipoConceptoEnum $tipo, float $montoBase, bool $activo, ?int $solicitadoPor = null): ConceptoPago
    {
        $concepto->update([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);

        if ((float) $concepto->monto_base !== $montoBase) {
            $this->solicitudesCambioMonto->solicitar($concepto, $montoBase, $solicitadoPor);
        }

        return $concepto->refresh();
    }
}
