<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Asistencia', 'subtitulo' => $estudiante->nombreCompleto().' · DNI '.$estudiante->dni])

    <table class="datos">
        <tr><td class="etiqueta">Sesiones registradas</td><td class="valor">{{ $asistencia['totalSesiones'] }}</td></tr>
        <tr><td class="etiqueta">Asistencias</td><td class="valor">{{ $asistencia['totalPresentes'] }}</td></tr>
        <tr><td class="etiqueta">% Asistencia</td><td class="valor">{{ $asistencia['porcentaje'] !== null ? $asistencia['porcentaje'].'%' : '—' }}</td></tr>
    </table>

    <table class="tabla-pdf">
        <thead><tr><th>Fecha</th><th>Curso</th><th>Semestre</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($asistencia['registros'] as $registro)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $registro->horario->curso->nombre }}</td>
                    <td>{{ $registro->horario->grado->nombre ?? '—' }}</td>
                    <td>{{ $registro->estado->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="sin-datos">Sin asistencias registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie-generado">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
