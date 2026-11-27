<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Documentos', 'subtitulo' => $estudiante->nombreCompleto().' · DNI '.$estudiante->dni])

    <div class="seccion-titulo">Subidos al matricularse</div>
    <table class="tabla-pdf">
        <thead><tr><th>Documento</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($documentosSubidos as $documento)
                <tr><td>{{ $documento->tipo->label() }}</td><td>{{ $documento->verificado ? 'Verificado' : 'Pendiente' }}</td></tr>
            @empty
                <tr><td colspan="2" class="sin-datos">No se han subido documentos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">Emitidos por la institución</div>
    <table class="tabla-pdf">
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
                <tr><td colspan="4" class="sin-datos">No se han emitido certificados ni constancias.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">Libretas generadas</div>
    <table class="tabla-pdf">
        <thead><tr><th>Período de matrícula</th><th>Generada</th><th>Entrega</th></tr></thead>
        <tbody>
            @forelse ($libretas as $libreta)
                <tr>
                    <td>{{ $libreta->ciclo->nombre }}</td>
                    <td>{{ $libreta->generado_en?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $libreta->entregado_en ? 'Entregada' : 'Sin entregar' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="sin-datos">No se han generado libretas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie-generado">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
