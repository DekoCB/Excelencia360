<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')

        .ok { color: #15803D; }
        .danger { color: #B91C1C; }
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Caja chica', 'subtitulo' => config('institucion.nombre').' · '.$mesLabel.' · generado el '.now()->format('d/m/Y H:i')])

    <table class="datos">
        <tr>
            <td class="etiqueta">Saldo actual del fondo</td>
            <td class="valor {{ $saldoActual >= 0 ? 'ok' : 'danger' }}">S/ {{ number_format($saldoActual, 2) }}</td>
        </tr>
    </table>

    <div class="seccion-titulo">Movimientos de {{ $mesLabel }}</div>
    <table class="tabla-pdf">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Categoría / descripción</th>
                <th>Registrado por</th>
                <th class="monto-col">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $movimiento)
                <tr>
                    <td>{{ $movimiento->fecha->format('d/m/Y') }}</td>
                    <td>{{ $movimiento->tipo->label() }}</td>
                    <td>{{ $movimiento->categoria?->label() ?? $movimiento->tipo->label() }}{{ $movimiento->descripcion ? " — {$movimiento->descripcion}" : '' }}</td>
                    <td>{{ $movimiento->registradoPor?->name ?? '—' }}</td>
                    <td class="monto-col {{ $movimiento->tipo->sumaAlSaldo() ? 'ok' : 'danger' }}">
                        {{ $movimiento->tipo->sumaAlSaldo() ? '+' : '−' }} S/ {{ number_format((float) $movimiento->monto, 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="sin-datos">Sin movimientos en {{ $mesLabel }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie-generado">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
