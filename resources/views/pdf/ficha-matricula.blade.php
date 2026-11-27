<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Ficha de matrícula', 'subtitulo' => config('institucion.nombre').' · '.$matricula->ciclo->nombre])

    @php $estudiante = $matricula->estudiante; @endphp

    <div class="seccion-titulo">Datos del estudiante</div>
    <table class="datos">
        <tr><td class="etiqueta">Nombres y apellidos</td><td class="valor">{{ $estudiante?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td class="valor">{{ $estudiante?->dni ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Fecha de nacimiento</td><td class="valor">{{ $estudiante?->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Dirección</td><td class="valor">{{ $estudiante?->direccion ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Celular</td><td class="valor">{{ $estudiante?->celular ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Correo</td><td class="valor">{{ $estudiante?->email ?? '—' }}</td></tr>
    </table>

    @if ($estudiante?->es_menor_edad && $estudiante->apoderado)
        <div class="seccion-titulo">Datos del apoderado</div>
        <table class="datos">
            <tr><td class="etiqueta">Nombres y apellidos</td><td class="valor">{{ $estudiante->apoderado->nombres }}</td></tr>
            <tr><td class="etiqueta">DNI</td><td class="valor">{{ $estudiante->apoderado->dni }}</td></tr>
            <tr><td class="etiqueta">Parentesco</td><td class="valor">{{ $estudiante->apoderado->parentesco }}</td></tr>
            <tr><td class="etiqueta">Celular</td><td class="valor">{{ $estudiante->apoderado->celular }}</td></tr>
        </table>
    @endif

    <div class="seccion-titulo">Datos de la matrícula</div>
    <table class="datos">
        <tr><td class="etiqueta">Período de matrícula</td><td class="valor">{{ $matricula->ciclo->nombre }}</td></tr>
        <tr><td class="etiqueta">Semestre</td><td class="valor">{{ $matricula->grado->nombre }}</td></tr>
        <tr><td class="etiqueta">Fecha de matrícula</td><td class="valor">{{ $matricula->fecha_matricula->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Estado</td><td class="valor">{{ $matricula->estado->label() }}</td></tr>
        @if ($matricula->observaciones)
            <tr><td class="etiqueta">Observaciones</td><td class="valor">{{ $matricula->observaciones }}</td></tr>
        @endif
    </table>

    <p class="pie-generado">Ficha N.° {{ str_pad((string) $matricula->id, 6, '0', STR_PAD_LEFT) }} · generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
