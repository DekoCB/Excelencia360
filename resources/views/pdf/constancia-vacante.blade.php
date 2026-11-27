<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Constancia de vacante'])

    <p class="cuerpo-carta">
        {{ mb_strtoupper(config('institucion.nombre')) }} (RUC {{ config('institucion.ruc') }}) deja constancia de que
        <strong>{{ $matricula->estudiante?->nombreCompleto() ?? '—' }}</strong>, identificado(a) con DNI
        <strong>{{ $matricula->estudiante?->dni ?? '—' }}</strong>, cuenta con vacante confirmada para cursar
        <strong>{{ $matricula->grado->nombre }}</strong> durante el ciclo
        <strong>{{ $matricula->ciclo->nombre }}</strong>, con matrícula registrada el
        {{ $matricula->fecha_matricula->format('d/m/Y') }}.
    </p>

    <p class="cuerpo-carta">
        Se expide la presente constancia a solicitud del interesado para los fines que estime conveniente.
    </p>

    <div class="firma-carta">
        <div class="linea"></div>
        <p class="nombre">{{ mb_strtoupper(config('institucion.gerente_general')) }}</p>
        <p>GERENTE GENERAL</p>
        <p>{{ mb_strtoupper(config('institucion.nombre')) }}</p>
    </div>

    <p class="pie-generado">Constancia N.° {{ str_pad((string) $matricula->id, 6, '0', STR_PAD_LEFT) }} · generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
