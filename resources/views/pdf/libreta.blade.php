<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.encabezado td { vertical-align: middle; }
        .logo-celda { width: 64px; padding-right: 14px; }
        .logo-celda img { width: 56px; }
        .institucion { font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; color: #165D96; margin: 0 0 2px; }
        .colegio-nombre { font-size: 18px; font-weight: bold; color: #0F7A78; margin: 0; line-height: 1.15; }
        .colegio-nombre span { display: block; font-size: 25px; color: #18BEBC; }
        .colegio-subtitulo { font-size: 10.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 2px solid #18BEBC; margin: 8px 0 18px; }

        h1 { text-align: center; font-size: 19px; margin: 0 0 18px; letter-spacing: 0.05em; text-transform: uppercase; color: #0F7A78; }

        .periodo { margin-top: 10px; }
        .periodo.salto { page-break-before: always; }

        .periodo-titulo { background: #18BEBC; color: #fff; font-size: 12px; font-weight: bold; letter-spacing: 0.04em; text-transform: uppercase; padding: 7px 10px; border-radius: 4px 4px 0 0; }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 0; background: #F3FBFB; border: 1px solid #DBDFE6; border-top: none; }
        table.datos td { padding: 5px 10px; font-size: 11px; }
        table.datos td.etiqueta { color: #165D96; width: 18%; text-transform: uppercase; letter-spacing: 0.03em; font-size: 9.5px; }
        table.datos td.valor { font-weight: bold; color: #1B1F27; }

        table.calificaciones { width: 100%; border-collapse: collapse; margin: 10px 0 0; }
        table.calificaciones thead td { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.02em; color: #fff; background: #165D96; padding: 6px 6px; text-align: center; }
        table.calificaciones thead td.col-unidad { text-align: left; }
        table.calificaciones tbody td { padding: 6px; border-bottom: 1px solid #EEF0F3; text-align: center; font-size: 10.5px; }
        table.calificaciones tbody td.col-unidad { text-align: left; }
        table.calificaciones tbody tr:nth-child(even) { background: #F8FAFB; }
        table.calificaciones td.pf { font-weight: bold; color: #0F7A78; }
        .sin-datos { color: #8891A0; font-style: italic; text-align: center; padding: 14px; }

        .estado-pill { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; letter-spacing: 0.03em; }
        .estado-pill.aprobado { background: #DCFCE7; color: #15803D; }
        .estado-pill.desaprobado { background: #FEE2E2; color: #B91C1C; }

        table.totales { width: 100%; border-collapse: collapse; margin-top: 0; background: #12225C; }
        table.totales td { padding: 7px 10px; font-size: 10px; color: #fff; text-align: center; letter-spacing: 0.02em; }
        table.totales td span { display: block; font-size: 13px; font-weight: bold; margin-top: 2px; color: #18BEBC; }

        p.cierre { text-align: right; margin: 26px 0 0; font-size: 11px; }

        .firma { margin-top: 56px; text-align: center; }
        .firma .linea { border-top: 1px solid #1B1F27; width: 220px; margin: 0 auto; }
        .firma p { margin: 4px 0 0; text-align: center; font-size: 11px; }
        .firma .nombre { font-weight: bold; }

        .pie-codigo { margin-top: 18px; font-size: 10px; color: #8891A0; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            @if ($logoInstitucion = \App\Shared\Support\Institucion::emblemaPath())
                <td class="logo-celda"><img src="{{ $logoInstitucion }}" alt="{{ config('institucion.nombre') }}"></td>
            @endif
            <td>
                <p class="colegio-nombre">GRUPO<span>EXCELENCIA 360</span></p>
                <p class="colegio-subtitulo">{{ config('institucion.actividad') }} · RUC {{ config('institucion.ruc') }}</p>
            </td>
        </tr>
    </table>
    <hr class="regla">

    <h1>Boleta de Notas</h1>

    <table class="datos" style="border-radius: 4px; border: 1px solid #DBDFE6;">
        <tr>
            <td class="etiqueta">Estudiante</td>
            <td class="valor">{{ $estudiante->nombreCompleto() }}</td>
            <td class="etiqueta">DNI</td>
            <td class="valor">{{ $estudiante->dni }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Modalidad</td>
            <td class="valor" colspan="3">{{ $modalidadTexto }}</td>
        </tr>
    </table>

    @forelse ($periodos as $i => $periodo)
        <div class="periodo {{ $i > 0 ? 'salto' : '' }}">
            <div class="periodo-titulo">Periodo académico: {{ $periodo['periodoAcademicoRomano'] }}</div>

            <table class="datos">
                <tr>
                    <td class="etiqueta">Carrera</td>
                    <td class="valor">{{ $periodo['programaEstudio']?->nombre ?? '—' }}</td>
                    <td class="etiqueta">Semestre</td>
                    <td class="valor">{{ $periodo['grado']?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="etiqueta">Período de matrícula</td>
                    <td class="valor" colspan="3">{{ $periodo['ciclo']?->nombre ?? '—' }}</td>
                </tr>
            </table>

            @if ($periodo['cursos']->isNotEmpty())
                <table class="calificaciones">
                    <thead>
                        <tr>
                            <td style="width: 24px;">N°</td>
                            <td style="width: 70px;">Código</td>
                            <td class="col-unidad">Unidad Didáctica</td>
                            <td style="width: 32px;">CND</td>
                            <td style="width: 32px;">SEC</td>
                            <td style="width: 32px;">CRD</td>
                            <td style="width: 40px;">PF</td>
                            <td style="width: 70px;">Estado</td>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periodo['cursos'] as $n => $curso)
                            <tr>
                                <td>{{ $n + 1 }}</td>
                                <td>{{ $curso['codigo'] }}</td>
                                <td class="col-unidad">{{ $curso['nombre'] }}</td>
                                <td>{{ $curso['cnd'] }}</td>
                                <td></td>
                                <td>{{ $curso['creditos'] ?? '—' }}</td>
                                <td class="pf">{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 2) : '—' }}</td>
                                <td>
                                    @if ($curso['estado'])
                                        <span class="estado-pill {{ $curso['estado'] === 'APROBADO' ? 'aprobado' : 'desaprobado' }}">{{ $curso['estado'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="totales">
                    <tr>
                        <td>Total créditos matriculados<span>{{ $periodo['totalCreditos'] }}</span></td>
                        <td>Cursos aprobados<span>{{ $periodo['cursosAprobados'] }}/{{ $periodo['cursos']->count() }}</span></td>
                        <td>Promedio ponderado<span>{{ $periodo['promedioPonderado'] !== null ? number_format($periodo['promedioPonderado'], 2) : '—' }}</span></td>
                    </tr>
                </table>
            @else
                <p class="sin-datos">NO EXISTEN REGISTROS DE NOTAS</p>
            @endif
        </div>
    @empty
        <p class="sin-datos">El estudiante no tiene matrículas registradas.</p>
    @endforelse

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
