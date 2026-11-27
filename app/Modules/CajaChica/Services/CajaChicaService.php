<?php

declare(strict_types=1);

namespace App\Modules\CajaChica\Services;

use App\Modules\CajaChica\Enums\TipoMovimientoCajaChicaEnum;
use App\Modules\CajaChica\Models\CajaChicaMovimiento;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Fondo fijo de caja chica -- aparte de Flujo de Caja (cobranza/egresos
 * grandes), para gastos menores del día a día. El saldo se calcula a
 * partir de los movimientos (nunca se guarda en una columna aparte, para
 * que nunca pueda desincronizarse de su historial).
 */
class CajaChicaService
{
    public function saldoActual(): float
    {
        $ingresos = (float) CajaChicaMovimiento::query()
            ->whereIn('tipo', [TipoMovimientoCajaChicaEnum::ASIGNACION, TipoMovimientoCajaChicaEnum::REPOSICION])
            ->sum('monto');

        $gastos = (float) CajaChicaMovimiento::query()
            ->where('tipo', TipoMovimientoCajaChicaEnum::GASTO)
            ->sum('monto');

        return $ingresos - $gastos;
    }

    /**
     * true si el fondo ya tiene una asignación inicial -- antes de la
     * primera, la pantalla pide "Asignar fondo" en vez de "Reponer fondo".
     */
    public function tieneFondoAsignado(): bool
    {
        return CajaChicaMovimiento::query()->where('tipo', TipoMovimientoCajaChicaEnum::ASIGNACION)->exists();
    }

    /**
     * @param  array{categoria: CategoriaEgresoEnum, descripcion: ?string, monto: float, fecha: string}  $datos
     */
    public function registrarGasto(array $datos, ?UploadedFile $comprobante, ?int $registradoPor): CajaChicaMovimiento
    {
        /** @var CajaChicaMovimiento $movimiento */
        $movimiento = CajaChicaMovimiento::query()->create([
            'tipo' => TipoMovimientoCajaChicaEnum::GASTO,
            'categoria' => $datos['categoria'],
            'descripcion' => $datos['descripcion'],
            'monto' => $datos['monto'],
            'fecha' => $datos['fecha'],
            'registrado_por' => $registradoPor,
        ]);

        if ($comprobante) {
            $movimiento->addMedia($comprobante)->toMediaCollection('comprobante');
        }

        return $movimiento;
    }

    public function asignarOReponerFondo(float $monto, ?int $registradoPor): CajaChicaMovimiento
    {
        $tipo = $this->tieneFondoAsignado() ? TipoMovimientoCajaChicaEnum::REPOSICION : TipoMovimientoCajaChicaEnum::ASIGNACION;

        return CajaChicaMovimiento::query()->create([
            'tipo' => $tipo,
            'monto' => $monto,
            'fecha' => now()->toDateString(),
            'registrado_por' => $registradoPor,
        ]);
    }

    /**
     * @return Collection<int, CajaChicaMovimiento>
     */
    public function movimientosDelPeriodo(Carbon $inicio, Carbon $fin): Collection
    {
        return CajaChicaMovimiento::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->with('registradoPor')
            ->latest('fecha')
            ->latest('id')
            ->get();
    }
}
