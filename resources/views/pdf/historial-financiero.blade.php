<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Situación financiera', 'subtitulo' => $estudiante->nombreCompleto().' · DNI '.$estudiante->dni])

    <table class="datos">
        <tr><td class="etiqueta">Pagado</td><td class="valor">S/ {{ number_format($resumenPagos['totalPagado'], 2) }}</td></tr>
        <tr><td class="etiqueta">Pendiente</td><td class="valor">S/ {{ number_format($resumenPagos['totalPendiente'], 2) }}</td></tr>
        <tr><td class="etiqueta">Exonerado</td><td class="valor">S/ {{ number_format($resumenPagos['totalExonerado'], 2) }}</td></tr>
    </table>

    @if ($resumenPagos['cuotasVencidas']->isNotEmpty())
        <div class="seccion-titulo">Cuotas vencidas</div>
        <table class="tabla-pdf">
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
        <div class="seccion-titulo">Cuotas pendientes</div>
        <table class="tabla-pdf">
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
        <div class="seccion-titulo">Cargos adicionales pendientes</div>
        <table class="tabla-pdf">
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

    <div class="seccion-titulo">Detalle de pagos</div>
    <table class="tabla-pdf">
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
                <tr><td colspan="5" class="sin-datos">Sin pagos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie-generado">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
