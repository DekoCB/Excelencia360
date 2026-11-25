@props(['historial'])

<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <h2 class="text-sm font-semibold text-ink">Situación de pagos</h2>
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-md bg-ok/10 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-ok">Pagado</p>
            <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPagado'], 2) }}</p>
        </div>
        <div class="rounded-md bg-warn/10 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-warn">Pendiente</p>
            <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPendiente'], 2) }}</p>
        </div>
        <div class="rounded-md bg-surface-2 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">Exonerado</p>
            <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalExonerado'], 2) }}</p>
        </div>
    </div>

    @if ($historial['resumenPagos']['cuotasVencidas']->isNotEmpty())
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-danger">Cuotas vencidas</h3>
        <div class="mt-2 divide-y divide-border">
            @foreach ($historial['resumenPagos']['cuotasVencidas'] as $cuota)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="text-ink-dim">Cuota {{ $cuota->numero }} · {{ $cuota->planPago->matricula?->grado->nombre }} · {{ $cuota->planPago->matricula?->ciclo->nombre }}</span>
                    <span class="text-danger">S/ {{ number_format((float) $cuota->monto, 2) }} · venció {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($historial['resumenPagos']['cuotasPendientes']->isNotEmpty())
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-warn">Cuotas pendientes</h3>
        <div class="mt-2 divide-y divide-border">
            @foreach ($historial['resumenPagos']['cuotasPendientes'] as $cuota)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="text-ink-dim">Cuota {{ $cuota->numero }} · {{ $cuota->planPago->matricula?->grado->nombre }} · {{ $cuota->planPago->matricula?->ciclo->nombre }}</span>
                    <span class="text-warn">S/ {{ number_format($cuota->saldoPendiente(), 2) }} · vence {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($historial['resumenPagos']['cargosAdicionalesPendientes']->isNotEmpty())
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-warn">Cargos adicionales pendientes</h3>
        <div class="mt-2 divide-y divide-border">
            @foreach ($historial['resumenPagos']['cargosAdicionalesPendientes'] as $cargo)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="text-ink-dim">{{ $cargo->concepto }}</span>
                    <span class="text-warn">S/ {{ number_format($cargo->saldoPendiente(), 2) }} pendiente</span>
                </div>
            @endforeach
        </div>
    @endif

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Detalle de pagos</h3>
    <div class="mt-2 divide-y divide-border">
        @forelse ($historial['pagos'] as $pago)
            <div class="flex items-center justify-between gap-4 py-3 text-sm">
                <div>
                    <p class="text-ink">{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</p>
                    <p class="text-xs text-ink-faint">{{ $pago->fecha_pago->format('d/m/Y') }} · {{ $pago->medioPagoResumen() }}</p>
                    @if ($pago->partes->count() > 1)
                        <p class="text-xs text-ink-faint">
                            {{ $pago->partes->map(fn ($parte) => 'S/ '.number_format((float) $parte->monto, 2).' '.$parte->metodoConNota())->implode(' + ') }}
                        </p>
                    @endif
                    @if ($pago->estado->value === 'rechazado' && $pago->motivo_rechazo)
                        <p class="text-xs text-danger">{{ $pago->motivo_rechazo }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs',
                        'bg-ok/10 text-ok' => $pago->estado->value === 'aprobado',
                        'bg-warn/10 text-warn' => $pago->estado->value === 'pendiente',
                        'bg-danger/10 text-danger' => $pago->estado->value === 'rechazado',
                    ])>{{ $pago->estado->label() }}</span>
                    <x-recibo-enlaces :recibo="$pago->recibo" />
                </div>
            </div>
        @empty
            <p class="py-4 text-sm text-ink-faint">Sin pagos registrados.</p>
        @endforelse
    </div>
</div>
