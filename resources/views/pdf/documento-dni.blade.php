<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')

        .cara { margin: 0 0 24px; text-align: center; }
        .cara p.etiqueta-cara { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #5B6472; margin: 0 0 6px; text-align: center; }
        .cara img { max-width: 100%; max-height: 320px; border: 1px solid #D6DAE3; border-radius: 4px; }
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => $documento->tipo->label(), 'subtitulo' => $documento->estudiante->nombreCompleto().' · DNI '.$documento->estudiante->dni])

    <div class="cara">
        <p class="etiqueta-cara">Cara</p>
        <img src="{{ $caraUrl }}" alt="Cara del DNI">
    </div>

    <div class="cara">
        <p class="etiqueta-cara">Sello / reverso</p>
        <img src="{{ $reversoUrl }}" alt="Reverso del DNI">
    </div>
</body>
</html>
