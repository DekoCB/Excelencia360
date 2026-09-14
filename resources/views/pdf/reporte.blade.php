<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1B1F27; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitulo { color: #5B6472; margin-bottom: 16px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F0F2F5; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border-bottom: 1px solid #DBDFE6; }
        td { padding: 5px 6px; border-bottom: 1px solid #EEF0F3; }
    </style>
</head>
<body>
    <h1>Reporte — {{ $titulo }}</h1>
    <p class="subtitulo">{{ config('institucion.nombre') }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th>{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(count($columnas), 1) }}">Sin datos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
