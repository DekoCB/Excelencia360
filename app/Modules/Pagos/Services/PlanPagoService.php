<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanPagoService
{
    /**
     * Si no se pasa $cuotasPersonalizadas, reparte $montoTotal en partes
     * iguales entre $numeroCuotas (la última absorbe el redondeo) con
     * vencimientos mensuales desde la fecha de matrícula -- el
     * comportamiento de siempre. Si se pasa, se usa tal cual: debe traer
     * exactamente $numeroCuotas->value elementos, uno por cada cuota, en
     * orden (monto y fecha de vencimiento propios de cada una).
     *
     * @param  list<array{monto: float, fecha_vencimiento: string}>|null  $cuotasPersonalizadas
     */
    public function crear(Matricula $matricula, NumeroCuotasEnum $numeroCuotas, float $montoTotal, ?array $cuotasPersonalizadas = null): PlanPago
    {
        if (PlanPago::query()->where('matricula_id', $matricula->id)->exists()) {
            throw ValidationException::withMessages([
                'matricula' => 'Este estudiante ya tiene un plan de pago para esta matrícula.',
            ]);
        }

        return DB::transaction(function () use ($matricula, $numeroCuotas, $montoTotal, $cuotasPersonalizadas) {
            $plan = PlanPago::query()->create([
                'matricula_id' => $matricula->id,
                'numero_cuotas' => $numeroCuotas->value,
                'monto_total' => $montoTotal,
            ]);

            if ($cuotasPersonalizadas !== null) {
                foreach ($cuotasPersonalizadas as $indice => $cuota) {
                    Cuota::query()->create([
                        'plan_pago_id' => $plan->id,
                        'numero' => $indice + 1,
                        'monto' => $cuota['monto'],
                        'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                    ]);
                }

                return $plan;
            }

            $montoPorCuota = round($montoTotal / $numeroCuotas->value, 2);
            $montoAcumulado = 0.0;

            for ($numero = 1; $numero <= $numeroCuotas->value; $numero++) {
                $esUltima = $numero === $numeroCuotas->value;
                $monto = $esUltima ? round($montoTotal - $montoAcumulado, 2) : $montoPorCuota;
                $montoAcumulado += $monto;

                Cuota::query()->create([
                    'plan_pago_id' => $plan->id,
                    'numero' => $numero,
                    'monto' => $monto,
                    'fecha_vencimiento' => $matricula->fecha_matricula->copy()->addMonths($numero),
                ]);
            }

            return $plan;
        });
    }

    public function planDe(Matricula $matricula): ?PlanPago
    {
        return PlanPago::query()->where('matricula_id', $matricula->id)->with('cuotas')->first();
    }

    /**
     * Cambia el monto total del plan y reparte la diferencia entre las
     * cuotas todavía pendientes (nunca las ya pagadas ni las exoneradas,
     * cuyo monto ya quedó resuelto): la última pendiente absorbe el
     * redondeo, igual que crear(). El número y la fecha de vencimiento de
     * cada cuota no cambian, solo su monto.
     */
    public function editarMontoTotal(PlanPago $plan, float $nuevoMontoTotal): PlanPago
    {
        if ($nuevoMontoTotal <= 0) {
            throw ValidationException::withMessages([
                'montoTotal' => 'El monto total debe ser mayor a cero.',
            ]);
        }

        $plan->loadMissing('cuotas');

        $montoResuelto = (float) $plan->cuotas
            ->whereIn('estado', [EstadoCuotaEnum::PAGADO, EstadoCuotaEnum::EXONERADO])
            ->sum('monto');

        $cuotasPendientes = $plan->cuotas
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->sortBy('numero')
            ->values();

        if ($nuevoMontoTotal < $montoResuelto) {
            throw ValidationException::withMessages([
                'montoTotal' => 'El nuevo monto no puede ser menor a lo ya pagado y exonerado (S/ '.number_format($montoResuelto, 2).').',
            ]);
        }

        if ($cuotasPendientes->isEmpty()) {
            throw ValidationException::withMessages([
                'montoTotal' => 'Este plan ya no tiene cuotas pendientes: no hay nada que repartir con el nuevo monto.',
            ]);
        }

        return DB::transaction(function () use ($plan, $nuevoMontoTotal, $cuotasPendientes, $montoResuelto) {
            $montoPendienteNuevo = round($nuevoMontoTotal - $montoResuelto, 2);
            $montoPorCuota = round($montoPendienteNuevo / $cuotasPendientes->count(), 2);
            $montoAcumulado = 0.0;

            foreach ($cuotasPendientes as $indice => $cuota) {
                $esUltima = $indice === $cuotasPendientes->count() - 1;
                $monto = $esUltima ? round($montoPendienteNuevo - $montoAcumulado, 2) : $montoPorCuota;
                $montoAcumulado += $monto;

                $cuota->update(['monto' => $monto]);
            }

            $plan->update(['monto_total' => $nuevoMontoTotal]);

            return $plan->fresh('cuotas');
        });
    }
}
