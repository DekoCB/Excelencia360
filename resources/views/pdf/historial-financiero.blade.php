<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1B1F27; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitulo { color: #5B6472; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th { background: #F0F2F5; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border-bottom: 1px solid #DBDFE6; }
        td { padding: 5px 6px; border-bottom: 1px solid #EEF0F3; }
        .etiqueta { color: #5B6472; width: 30%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; }
        .seccion { font-size: 13px; font-weight: bold; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; }
        .subseccion { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin-top: 10px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Situación financiera</h1>
    <p class="subtitulo">{{ $estudiante->nombreCompleto() }} · DNI {{ $estudiante->dni }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <tr><td class="etiqueta">Pagado</td><td>S/ {{ number_format($resumenPagos['totalPagado'], 2) }}</td></tr>
        <tr><td class="etiqueta">Pendiente</td><td>S/ {{ number_format($resumenPagos['totalPendiente'], 2) }}</td></tr>
        <tr><td class="etiqueta">Exonerado</td><td>S/ {{ number_format($resumenPagos['totalExonerado'], 2) }}</td></tr>
    </table>

    @if ($resumenPagos['cuotasVencidas']->isNotEmpty())
        <div class="subseccion">Cuotas vencidas</div>
        <table>
            <thead><tr><th>Cuota</th><th>Semestre</th><th>Período de matrícula</th><th>Monto</th><th>Venció</th></tr></thead>
            <tbody>
                @foreach ($resumenPagos['cuotasVencidas'] as $cuota)
                    <tr>
                        <td>{{ $cuota->numero }}</td>
                        <td>{{ $cuota->planPago->matricula?->grado->nombre }}</td>
                        <td>{{ $cuota->planPago->matricula?->ciclo->nombre }}</td>
                        <td>S/ {{ number_format((float) $cuota->monto, 2) }}</td>
                        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($resumenPagos['cuotasPendientes']->isNotEmpty())
        <div class="subseccion">Cuotas pendientes</div>
        <table>
            <thead><tr><th>Cuota</th><th>Semestre</th><th>Período de matrícula</th><th>Saldo</th><th>Vence</th></tr></thead>
            <tbody>
                @foreach ($resumenPagos['cuotasPendientes'] as $cuota)
                    <tr>
                        <td>{{ $cuota->numero }}</td>
                        <td>{{ $cuota->planPago->matricula?->grado->nombre }}</td>
                        <td>{{ $cuota->planPago->matricula?->ciclo->nombre }}</td>
                        <td>S/ {{ number_format($cuota->saldoPendiente(), 2) }}</td>
                        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($resumenPagos['cargosAdicionalesPendientes']->isNotEmpty())
        <div class="subseccion">Cargos adicionales pendientes</div>
        <table>
            <thead><tr><th>Concepto</th><th>Saldo</th><th>Vence</th><th>Estado</th></tr></thead>
            <tbody>
                @foreach ($resumenPagos['cargosAdicionalesPendientes'] as $cargo)
                    <tr>
                        <td>{{ $cargo->concepto }}</td>
                        <td>S/ {{ number_format($cargo->saldoPendiente(), 2) }}</td>
                        <td>{{ $cargo->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $cargo->estaVencido() ? 'Vencido' : 'Pendiente' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="subseccion">Detalle de pagos</div>
    <table>
        <thead>
            <tr><th>Fecha</th><th>Concepto</th><th>Método</th><th>Estado</th><th>Monto</th></tr>
        </thead>
        <tbody>
            @forelse ($pagos as $pago)
                <tr>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</td>
                    <td>{{ $pago->metodo->label() }}</td>
                    <td>{{ $pago->estado->label() }}{{ $pago->estado->value === 'rechazado' && $pago->motivo_rechazo ? " — {$pago->motivo_rechazo}" : '' }}</td>
                    <td>S/ {{ number_format((float) $pago->monto, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin pagos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
