<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')

        table.resumen { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.resumen td { width: 33.33%; padding: 10px 12px; border: 1px solid #DBDFE6; }
        .resumen .etiqueta { margin: 0 0 4px; }
        .resumen .monto { font-size: 16px; font-weight: bold; margin: 0; }
        .ok { color: #15803D; }
        .danger { color: #B91C1C; }
        .cuadrado { color: #15803D; }
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Flujo de caja', 'subtitulo' => config('institucion.nombre').' · '.$mesLabel.' · generado el '.now()->format('d/m/Y H:i')])

    <table class="resumen">
        <tr>
            <td>
                <p class="etiqueta">Ingresos</p>
                <p class="monto ok">S/ {{ number_format($ingresos, 2) }}</p>
            </td>
            <td>
                <p class="etiqueta">Egresos</p>
                <p class="monto danger">S/ {{ number_format($egresos, 2) }}</p>
            </td>
            <td>
                <p class="etiqueta">Saldo neto</p>
                <p class="monto {{ $saldoNeto >= 0 ? 'ok' : 'danger' }}">S/ {{ number_format($saldoNeto, 2) }}</p>
            </td>
        </tr>
    </table>

    @if (($turnosCerrados ?? collect())->isNotEmpty())
        <div class="seccion-titulo">Turnos de caja cerrados en el período</div>
        <table class="tabla-pdf">
            <thead>
                <tr>
                    <th>Turno</th>
                    <th>Responsable</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th class="monto-col">Inicial</th>
                    <th class="monto-col">Declarado</th>
                    <th class="monto-col">Calculado</th>
                    <th class="monto-col">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($turnosCerrados as $fila)
                    @php $turnoCerrado = $fila['turno']; @endphp
                    <tr>
                        <td>{{ $turnoCerrado->turno?->label() ?? '—' }}</td>
                        <td>{{ $turnoCerrado->responsable ?? $turnoCerrado->usuario->name }}</td>
                        <td>{{ $turnoCerrado->hora_apertura->format('d/m/Y H:i') }}</td>
                        <td>{{ $turnoCerrado->hora_cierre->format('d/m/Y H:i') }}</td>
                        <td class="monto-col">S/ {{ number_format((float) $turnoCerrado->monto_inicial, 2) }}</td>
                        <td class="monto-col">S/ {{ number_format((float) $turnoCerrado->monto_final_declarado, 2) }}</td>
                        <td class="monto-col">S/ {{ number_format($fila['montoCalculado'], 2) }}</td>
                        <td class="monto-col {{ abs($fila['diferencia']) < 0.01 ? 'cuadrado' : 'danger' }}">
                            {{ abs($fila['diferencia']) < 0.01 ? 'Cuadrado' : ($fila['diferencia'] > 0 ? '+' : '−').' S/ '.number_format(abs($fila['diferencia']), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="seccion-titulo">Movimientos de {{ $mesLabel }}</div>
    <table class="tabla-pdf">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Método</th>
                <th class="monto-col">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $movimiento)
                <tr>
                    <td>{{ $movimiento['fecha']->format('d/m/Y') }}</td>
                    <td>{{ $movimiento['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' }}</td>
                    <td>{{ $movimiento['concepto'] }}</td>
                    <td>{{ $movimiento['metodo'] }}</td>
                    <td class="monto-col">{{ $movimiento['tipo'] === 'ingreso' ? '+' : '−' }} S/ {{ number_format($movimiento['monto'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="sin-datos">Sin movimientos en {{ $mesLabel }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie-generado">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
