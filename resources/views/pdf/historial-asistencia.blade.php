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
    </style>
</head>
<body>
    <h1>Asistencia</h1>
    <p class="subtitulo">{{ $estudiante->nombreCompleto() }} · DNI {{ $estudiante->dni }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <tr><td class="etiqueta">Sesiones registradas</td><td>{{ $asistencia['totalSesiones'] }}</td></tr>
        <tr><td class="etiqueta">Asistencias</td><td>{{ $asistencia['totalPresentes'] }}</td></tr>
        <tr><td class="etiqueta">% Asistencia</td><td>{{ $asistencia['porcentaje'] !== null ? $asistencia['porcentaje'].'%' : '—' }}</td></tr>
    </table>

    <table>
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
                <tr><td colspan="4">Sin asistencias registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
