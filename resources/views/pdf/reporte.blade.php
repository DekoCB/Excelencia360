<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => $titulo, 'subtitulo' => config('institucion.nombre').' · generado el '.now()->format('d/m/Y H:i')])

    <table class="tabla-pdf">
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
                <tr><td colspan="{{ max(count($columnas), 1) }}" class="sin-datos">Sin datos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
