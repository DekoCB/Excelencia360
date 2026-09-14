<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 13px; color: #1B1F27; line-height: 1.6; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 32px; text-transform: uppercase; letter-spacing: 0.05em; }
        p { text-align: justify; }
        .firma { margin-top: 80px; text-align: center; }
        .linea { border-top: 1px solid #1B1F27; width: 240px; margin: 0 auto; padding-top: 6px; }
        .codigo { margin-top: 60px; font-size: 10px; color: #8891A0; text-align: center; }
    </style>
</head>
<body>
    <h1>Constancia de vacante</h1>

    <p>
        {{ mb_strtoupper(config('institucion.nombre')) }} (RUC {{ config('institucion.ruc') }}) deja constancia de que
        <strong>{{ $matricula->estudiante?->nombreCompleto() ?? '—' }}</strong>, identificado(a) con DNI
        <strong>{{ $matricula->estudiante?->dni ?? '—' }}</strong>, cuenta con vacante confirmada para cursar
        <strong>{{ $matricula->grado->nombre }}</strong> durante el ciclo
        <strong>{{ $matricula->ciclo->nombre }}</strong>, con matrícula registrada el
        {{ $matricula->fecha_matricula->format('d/m/Y') }}.
    </p>

    <p>
        Se expide la presente constancia a solicitud del interesado para los fines que estime conveniente.
    </p>

    <div class="firma">
        <div class="linea">Gerencia General · {{ config('institucion.nombre') }}</div>
    </div>

    <p class="codigo">Constancia N.° {{ str_pad((string) $matricula->id, 6, '0', STR_PAD_LEFT) }} · generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
