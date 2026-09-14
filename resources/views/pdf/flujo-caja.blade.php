<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1B1F27; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitulo { color: #5B6472; margin-bottom: 18px; }

        table.resumen { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.resumen td { width: 33.33%; padding: 10px 12px; border: 1px solid #DBDFE6; }
        .resumen .etiqueta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin: 0 0 4px; }
        .resumen .monto { font-size: 16px; font-weight: bold; margin: 0; }
        .ok { color: #15803D; }
        .danger { color: #B91C1C; }

        table.movimientos { width: 100%; border-collapse: collapse; }
        table.movimientos th { background: #F0F2F5; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border-bottom: 1px solid #DBDFE6; }
        table.movimientos td { padding: 6px 8px; border-bottom: 1px solid #EEF0F3; }
        .monto-col { text-align: right; white-space: nowrap; }
        .sin-datos { color: #8891A0; font-style: italic; }
    </style>
</head>
<body>
    <h1>Flujo de caja</h1>
    <p class="subtitulo">{{ config('institucion.nombre') }} · {{ $mesLabel }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

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

    <table class="movimientos">
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
</body>
</html>
