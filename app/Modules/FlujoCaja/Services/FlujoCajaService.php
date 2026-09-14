<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Services;

use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Ingresos = Pago aprobado (mismo criterio que ya usa el Dashboard, ver
 * dashboard/index.blade.php: ingresosDelMes/calcularIngresosPorSemana,
 * agregando por fecha_aprobacion). Egresos son registrados a mano acá.
 */
class FlujoCajaService
{
    /**
     * @param  array{categoria: CategoriaEgresoEnum, descripcion: ?string, monto: float, metodo: MetodoPagoEnum, fecha_egreso: string}  $datos
     */
    public function registrarEgreso(array $datos, ?UploadedFile $comprobante, ?int $registradoPor): Egreso
    {
        /** @var Egreso $egreso */
        $egreso = Egreso::query()->create([...$datos, 'registrado_por' => $registradoPor]);

        if ($comprobante) {
            $egreso->addMedia($comprobante)->toMediaCollection('comprobante');
        }

        return $egreso;
    }

    public function ingresosDelPeriodo(Carbon $inicio, Carbon $fin): float
    {
        return (float) Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->whereBetween('fecha_aprobacion', [$inicio, $fin])
            ->sum('monto');
    }

    public function egresosDelPeriodo(Carbon $inicio, Carbon $fin): float
    {
        return (float) Egreso::query()
            ->whereBetween('fecha_egreso', [$inicio, $fin])
            ->sum('monto');
    }

    /**
     * El libro de movimientos del periodo: ingresos (Pago aprobado) y
     * egresos normalizados a la misma forma y mezclados por fecha
     * descendente. Sin generics en el tipo de retorno: el TValue de
     * Collection no es covariante (ver LibretaService::resumenPorCursos()
     * para el mismo caso), así que ninguna anotación de forma de array
     * sobrevive a un ->map()+->concat(). Cada elemento es un array con las
     * claves "tipo" ('ingreso'|'egreso'), "fecha" (Carbon), "concepto"
     * (string), "metodo" (string), "monto" (float) y "comprobanteUrl"
     * (?string).
     */
    public function movimientosDelPeriodo(Carbon $inicio, Carbon $fin): SupportCollection
    {
        $ingresos = Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->whereBetween('fecha_aprobacion', [$inicio, $fin])
            ->with(['concepto', 'cargoAdicional', 'recibo'])
            ->get()
            ->map(fn (Pago $pago) => [
                'tipo' => 'ingreso',
                'fecha' => $pago->fecha_aprobacion ?? $pago->fecha_pago,
                'concepto' => $pago->nombreConcepto().($pago->detalle ? " — {$pago->detalle}" : ''),
                'metodo' => $pago->metodo->label(),
                'monto' => (float) $pago->monto,
                'comprobanteUrl' => $pago->recibo?->getFirstMediaUrl('pdf') ?: null,
            ]);

        $egresos = Egreso::query()
            ->whereBetween('fecha_egreso', [$inicio, $fin])
            ->get()
            ->map(fn (Egreso $egreso) => [
                'tipo' => 'egreso',
                'fecha' => $egreso->fecha_egreso,
                'concepto' => $egreso->categoria->label().($egreso->descripcion ? " — {$egreso->descripcion}" : ''),
                'metodo' => $egreso->metodo->label(),
                'monto' => (float) $egreso->monto,
                'comprobanteUrl' => $egreso->getFirstMediaUrl('comprobante') ?: null,
            ]);

        return $ingresos->concat($egresos)->sortByDesc('fecha')->values();
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    public function ingresosPorMes(int $meses = 6): array
    {
        return $this->serieMensual($meses, fn (Carbon $inicio, Carbon $fin) => $this->ingresosDelPeriodo($inicio, $fin));
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    public function egresosPorMes(int $meses = 6): array
    {
        return $this->serieMensual($meses, fn (Carbon $inicio, Carbon $fin) => $this->egresosDelPeriodo($inicio, $fin));
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    private function serieMensual(int $meses, \Closure $montoDelPeriodo): array
    {
        $labels = [];
        $datos = [];

        for ($mesesAtras = $meses - 1; $mesesAtras >= 0; $mesesAtras--) {
            $inicio = now()->subMonths($mesesAtras)->startOfMonth();
            $fin = now()->subMonths($mesesAtras)->endOfMonth();

            $labels[] = ucfirst($inicio->translatedFormat('M'));
            $datos[] = $montoDelPeriodo($inicio, $fin);
        }

        return [$labels, $datos];
    }
}
