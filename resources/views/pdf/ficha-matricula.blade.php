<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitulo { color: #5B6472; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        td { padding: 6px 8px; vertical-align: top; }
        .etiqueta { color: #5B6472; width: 35%; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        .seccion { font-size: 13px; font-weight: bold; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; }
        .codigo { margin-top: 40px; font-size: 10px; color: #8891A0; }
    </style>
</head>
<body>
    <h1>Ficha de Matrícula</h1>
    <p class="subtitulo">{{ config('institucion.nombre') }} · {{ $matricula->ciclo->nombre }}</p>

    @php $estudiante = $matricula->estudiante; @endphp

    <div class="seccion">Datos del estudiante</div>
    <table>
        <tr><td class="etiqueta">Nombres y apellidos</td><td>{{ $estudiante?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $estudiante?->dni ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Fecha de nacimiento</td><td>{{ $estudiante?->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Dirección</td><td>{{ $estudiante?->direccion ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Celular</td><td>{{ $estudiante?->celular ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Correo</td><td>{{ $estudiante?->email ?? '—' }}</td></tr>
    </table>

    @if ($estudiante?->es_menor_edad && $estudiante->apoderado)
        <div class="seccion">Datos del apoderado</div>
        <table>
            <tr><td class="etiqueta">Nombres y apellidos</td><td>{{ $estudiante->apoderado->nombres }}</td></tr>
            <tr><td class="etiqueta">DNI</td><td>{{ $estudiante->apoderado->dni }}</td></tr>
            <tr><td class="etiqueta">Parentesco</td><td>{{ $estudiante->apoderado->parentesco }}</td></tr>
            <tr><td class="etiqueta">Celular</td><td>{{ $estudiante->apoderado->celular }}</td></tr>
        </table>
    @endif

    <div class="seccion">Datos de la matrícula</div>
    <table>
        <tr><td class="etiqueta">Ciclo</td><td>{{ $matricula->ciclo->nombre }}</td></tr>
        <tr><td class="etiqueta">Grado</td><td>{{ $matricula->grado->nombre }}</td></tr>
        <tr><td class="etiqueta">Fecha de matrícula</td><td>{{ $matricula->fecha_matricula->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Estado</td><td>{{ $matricula->estado->label() }}</td></tr>
        @if ($matricula->observaciones)
            <tr><td class="etiqueta">Observaciones</td><td>{{ $matricula->observaciones }}</td></tr>
        @endif
    </table>

    <p class="codigo">Ficha N.° {{ str_pad((string) $matricula->id, 6, '0', STR_PAD_LEFT) }} · generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
