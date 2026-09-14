<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.encabezado td { vertical-align: middle; }
        .logo-celda { width: 78px; padding-right: 14px; }
        .logo-celda img { width: 70px; }
        .institucion { font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; color: #5B6472; margin: 0 0 2px; }
        .colegio-nombre { font-size: 18px; font-weight: bold; color: #12225C; margin: 0; line-height: 1.15; }
        .colegio-nombre span { display: block; font-size: 25px; }
        .colegio-subtitulo { font-size: 10.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 2px solid #12225C; margin: 8px 0 22px; }

        h1 { text-align: center; font-size: 20px; margin: 0 0 22px; letter-spacing: 0.05em; text-transform: uppercase; color: #12225C; }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.datos td { padding: 4px 0; font-size: 11.5px; }
        table.datos td.etiqueta { color: #5B6472; width: 32%; text-transform: uppercase; letter-spacing: 0.03em; font-size: 10px; }
        table.datos td.valor { font-weight: bold; color: #1B1F27; }

        .seccion { font-size: 13px; font-weight: bold; margin-top: 8px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; color: #12225C; }

        table.calificaciones { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.calificaciones thead td { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border-bottom: 1px solid #DBDFE6; padding: 6px 8px; }
        table.calificaciones tbody td { padding: 8px; border-bottom: 1px solid #EEF0F3; }
        table.calificaciones td.nota { text-align: center; font-weight: bold; width: 140px; }
        .sin-datos { color: #8891A0; font-style: italic; }

        .situacion { margin: 22px 0; padding: 10px 14px; text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 0.04em; text-transform: uppercase; border-radius: 4px; }
        .situacion.aprobado { background: #DCFCE7; color: #15803D; }
        .situacion.desaprobado { background: #FEE2E2; color: #B91C1C; }

        p.cierre { text-align: right; margin: 24px 0 0; }

        .firma { margin-top: 56px; text-align: center; }
        .firma .linea { border-top: 1px solid #1B1F27; width: 220px; margin: 0 auto; }
        .firma p { margin: 4px 0 0; text-align: center; }
        .firma .nombre { font-weight: bold; }

        .pie-codigo { margin-top: 18px; font-size: 10px; color: #8891A0; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            @if ($logoInstitucion = \App\Shared\Support\Institucion::logoPath())
                <td class="logo-celda"><img src="{{ $logoInstitucion }}" alt="{{ config('institucion.nombre') }}"></td>
            @endif
            <td>
                <p class="colegio-nombre">GRUPO<span>EXCELENCIA 360</span></p>
                <p class="colegio-subtitulo">{{ config('institucion.actividad') }} · RUC {{ config('institucion.ruc') }}</p>
            </td>
        </tr>
    </table>
    <hr class="regla">

    <h1>Libreta de Notas</h1>

    <table class="datos">
        <tr>
            <td class="etiqueta">Estudiante</td>
            <td class="valor">{{ $estudiante->nombreCompleto() }}</td>
            <td class="etiqueta">DNI</td>
            <td class="valor">{{ $estudiante->dni }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Modalidad</td>
            <td class="valor">{{ $modalidadTexto }}</td>
            <td class="etiqueta">Grado</td>
            <td class="valor">{{ $matricula?->grado?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Periodo promocional</td>
            <td class="valor">{{ $periodoPromocional }}</td>
            <td class="etiqueta">Ciclo</td>
            <td class="valor">{{ $ciclo->nombre }}</td>
        </tr>
    </table>

    <div class="seccion">Calificaciones</div>
    <table class="calificaciones">
        <thead>
            <tr>
                <td>Asignatura o actividades de aprendizaje</td>
                <td class="nota">Calificación final (Nota)</td>
            </tr>
        </thead>
        <tbody>
            @forelse ($cursos as $curso)
                <tr>
                    <td>{{ $curso['nombre'] }}</td>
                    <td class="nota">{{ $curso['letra'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="sin-datos">No hay cursos registrados para este ciclo.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($cursos->isNotEmpty())
        <div class="situacion {{ $situacionFinal === 'APROBADO' ? 'aprobado' : 'desaprobado' }}">
            Situación final: {{ $situacionFinal }}
        </div>
    @endif

    <p class="cierre">{{ config('institucion.ciudad') }}, {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="firma">
        <div class="linea"></div>
        <p class="nombre">{{ mb_strtoupper(config('institucion.gerente_general')) }}</p>
        <p>GERENTE GENERAL</p>
        <p>{{ mb_strtoupper(config('institucion.nombre')) }}</p>
    </div>

    <p class="pie-codigo">Generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
