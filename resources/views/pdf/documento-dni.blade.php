<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }

        h1 { font-size: 16px; margin: 0 0 4px; color: #12225C; }
        .subtitulo { font-size: 10.5px; color: #5B6472; margin: 0 0 24px; }

        .cara { margin: 0 0 24px; text-align: center; }
        .cara p.etiqueta { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #5B6472; margin: 0 0 6px; }
        .cara img { max-width: 100%; max-height: 320px; border: 1px solid #D6DAE3; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>{{ $documento->tipo->label() }}</h1>
    <p class="subtitulo">{{ $documento->estudiante->nombreCompleto() }} · DNI {{ $documento->estudiante->dni }}</p>

    <div class="cara">
        <p class="etiqueta">Cara</p>
        <img src="{{ $caraUrl }}" alt="Cara del DNI">
    </div>

    <div class="cara">
        <p class="etiqueta">Sello / reverso</p>
        <img src="{{ $reversoUrl }}" alt="Reverso del DNI">
    </div>
</body>
</html>
