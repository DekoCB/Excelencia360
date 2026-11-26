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
        .subseccion { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin-top: 10px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Documentos</h1>
    <p class="subtitulo">{{ $estudiante->nombreCompleto() }} · DNI {{ $estudiante->dni }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="subseccion">Subidos al matricularse</div>
    <table>
        <thead><tr><th>Documento</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($documentosSubidos as $documento)
                <tr><td>{{ $documento->tipo->label() }}</td><td>{{ $documento->verificado ? 'Verificado' : 'Pendiente' }}</td></tr>
            @empty
                <tr><td colspan="2">No se han subido documentos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="subseccion">Emitidos por la institución</div>
    <table>
        <thead><tr><th>Documento</th><th>N.°</th><th>Emisión</th><th>Entrega</th></tr></thead>
        <tbody>
            @forelse ($documentosEmitidos as $certificado)
                <tr>
                    <td>{{ $certificado->tipo->label() }}</td>
                    <td>{{ $certificado->numero }}</td>
                    <td>{{ $certificado->fecha_emision->format('d/m/Y') }}</td>
                    <td>{{ $certificado->entregado_en ? 'Entregado' : 'Sin entregar' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No se han emitido certificados ni constancias.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="subseccion">Libretas generadas</div>
    <table>
        <thead><tr><th>Período de matrícula</th><th>Generada</th><th>Entrega</th></tr></thead>
        <tbody>
            @forelse ($libretas as $libreta)
                <tr>
                    <td>{{ $libreta->ciclo->nombre }}</td>
                    <td>{{ $libreta->generado_en?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $libreta->entregado_en ? 'Entregada' : 'Sin entregar' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No se han generado libretas.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
