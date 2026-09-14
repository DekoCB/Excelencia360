<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PagoService
{
    public function __construct(
        private readonly ReciboService $recibos,
        private readonly BloqueoAccesoService $bloqueos,
    ) {}

    /**
     * Un pago puede cubrirse con más de una parte a la vez (ej. una mitad
     * en efectivo y la otra por Yape), pero queda como un solo registro:
     * cada elemento de $partes es un monto+método independiente, y su suma
     * es lo que se guarda como Pago::$monto. Pago::$metodo queda como el
     * método único si todas las partes usan el mismo, o "mixto" si no --
     * es solo un resumen para listados; el detalle real vive en partes().
     *
     * @param  list<array{monto: float, metodo: string, nota?: ?string}>  $partes
     */
    public function registrar(
        Estudiante $estudiante,
        ConceptoPago $concepto,
        array $partes,
        ?Cuota $cuota,
        ?UploadedFile $comprobante,
        ?int $registradoPor,
        ?string $detalle = null,
        ?string $observacion = null,
        ?string $fechaPago = null,
        ?CargoAdicional $cargoAdicional = null,
    ): Pago {
        if ($partes === []) {
            throw new InvalidArgumentException('Un pago necesita al menos una parte (monto y método).');
        }

        if ($cuota && Pago::query()->where('cuota_id', $cuota->id)->where('estado', EstadoPagoEnum::PENDIENTE)->exists()) {
            throw ValidationException::withMessages([
                'cuota' => 'Ya existe un pago pendiente de aprobación para esta cuota.',
            ]);
        }

        if ($cargoAdicional && Pago::query()->where('cargo_adicional_id', $cargoAdicional->id)->where('estado', EstadoPagoEnum::PENDIENTE)->exists()) {
            throw ValidationException::withMessages([
                'cargoAdicional' => 'Ya existe un pago pendiente de aprobación para este cargo.',
            ]);
        }

        $montoTotal = array_sum(array_column($partes, 'monto'));
        $metodosUnicos = collect($partes)->pluck('metodo')->unique();
        $metodo = $metodosUnicos->count() === 1 ? $metodosUnicos->first() : MetodoPagoEnum::MIXTO->value;

        // Fecha de pago: cuándo se recibió el dinero (editable, puede ser un
        // día anterior si se cobró en efectivo y recién se registra hoy) --
        // distinta de la fecha de emisión del recibo (Recibo::emitido_en,
        // siempre la del momento en que Tesorería aprueba), que no se toca.
        $fecha = $fechaPago !== null ? Carbon::parse($fechaPago) : now();

        return DB::transaction(function () use ($estudiante, $concepto, $detalle, $observacion, $cuota, $cargoAdicional, $montoTotal, $metodo, $registradoPor, $comprobante, $partes, $fecha) {
            /** @var Pago $pago */
            $pago = Pago::query()->create([
                'estudiante_id' => $estudiante->id,
                'concepto_id' => $concepto->id,
                'detalle' => $detalle,
                'observacion' => $observacion,
                'cuota_id' => $cuota?->id,
                'cargo_adicional_id' => $cargoAdicional?->id,
                'monto' => $montoTotal,
                'metodo' => $metodo,
                'estado' => EstadoPagoEnum::PENDIENTE,
                'registrado_por' => $registradoPor,
                'fecha_pago' => $fecha,
            ]);

            foreach ($partes as $parte) {
                $pago->partes()->create([
                    'monto' => $parte['monto'],
                    'metodo' => $parte['metodo'],
                    'nota' => $parte['nota'] ?? null,
                ]);
            }

            if ($comprobante) {
                $pago->addMedia($comprobante)->toMediaCollection('comprobante');
            }

            return $pago;
        });
    }

    public function aprobar(Pago $pago, int $aprobadoPor, SerieReciboEnum $serie): Pago
    {
        $this->validarPendiente($pago);

        DB::transaction(function () use ($pago, $aprobadoPor, $serie) {
            $pago->update([
                'estado' => EstadoPagoEnum::APROBADO,
                'aprobado_por' => $aprobadoPor,
                'fecha_aprobacion' => now(),
            ]);

            if ($pago->cuota) {
                // El pago recién aprobado puede ser parcial (ej. 40 de una
                // cuota de 80): la cuota solo pasa a "pagado" cuando la suma
                // de todos sus pagos aprobados cubre el monto completo, no
                // con el primer pago que se le vincule. Ver Cuota::saldoPendiente().
                $pago->cuota->update([
                    'estado' => $pago->cuota->saldoPendiente() <= 0.0 ? EstadoCuotaEnum::PAGADO : EstadoCuotaEnum::PENDIENTE,
                ]);
            }

            if ($pago->cargoAdicional) {
                // Mismo criterio que las cuotas: puede ser un pago parcial.
                $pago->cargoAdicional->update([
                    'estado' => $pago->cargoAdicional->saldoPendiente() <= 0.0 ? EstadoCuotaEnum::PAGADO : EstadoCuotaEnum::PENDIENTE,
                ]);
            }

            $this->recibos->emitir($pago, $serie);
            $this->bloqueos->evaluarYDesbloquear($pago->estudiante);
        });

        return $pago->refresh();
    }

    /**
     * Corrige el monto nominal de un cargo adicional (ej. se anotó mal el
     * precio de la Convalidación al matricular). No puede bajar del monto
     * ya cobrado -- mismo criterio que PlanPagoService::editarMontoTotal()
     * para cuotas. Si el cargo ya estaba "pagado" y el nuevo monto deja
     * saldo pendiente, vuelve a "pendiente": el estado siempre refleja el
     * saldo real, nunca al revés.
     */
    public function editarMontoCargoAdicional(CargoAdicional $cargoAdicional, float $nuevoMonto): CargoAdicional
    {
        if ($nuevoMonto <= 0) {
            throw ValidationException::withMessages([
                'montoCargoNuevo' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $montoPagado = $cargoAdicional->montoPagado();

        if ($nuevoMonto < $montoPagado) {
            throw ValidationException::withMessages([
                'montoCargoNuevo' => 'El nuevo monto no puede ser menor a lo ya pagado (S/ '.number_format($montoPagado, 2).').',
            ]);
        }

        $cargoAdicional->update([
            'monto' => $nuevoMonto,
            'estado' => $cargoAdicional->estado === EstadoCuotaEnum::PAGADO && $nuevoMonto > $montoPagado
                ? EstadoCuotaEnum::PENDIENTE
                : $cargoAdicional->estado,
        ]);

        return $cargoAdicional->fresh();
    }

    public function rechazar(Pago $pago, int $aprobadoPor, string $motivo): Pago
    {
        $this->validarPendiente($pago);

        $pago->update([
            'estado' => EstadoPagoEnum::RECHAZADO,
            'aprobado_por' => $aprobadoPor,
            'fecha_aprobacion' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        return $pago;
    }

    /**
     * @return Collection<int, Pago>
     */
    public function misPagos(Estudiante $estudiante): Collection
    {
        return Pago::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['concepto', 'cuota', 'cargoAdicional', 'recibo', 'partes'])
            ->latest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function pendientesDeAprobacion(): Collection
    {
        return Pago::query()
            ->where('estado', EstadoPagoEnum::PENDIENTE)
            ->with(['estudiante', 'concepto', 'cuota', 'cargoAdicional', 'partes'])
            ->oldest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function todos(): Collection
    {
        return Pago::query()
            ->with(['estudiante', 'concepto', 'cuota', 'cargoAdicional', 'recibo', 'partes'])
            ->latest('fecha_pago')
            ->get();
    }

    private function validarPendiente(Pago $pago): void
    {
        if ($pago->estado !== EstadoPagoEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => 'Este pago ya fue procesado y no puede modificarse.',
            ]);
        }
    }
}
