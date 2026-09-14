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
        .etiqueta { color: #5B6472; width: 30%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; }
        .seccion { font-size: 13px; font-weight: bold; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; }
        .subseccion { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin-top: 10px; margin-bottom: 4px; }
        .codigo { margin-top: 30px; font-size: 9px; color: #8891A0; }
    </style>
</head>
<body>
    <h1>Historial del estudiante</h1>
    <p class="subtitulo">{{ config('institucion.nombre') }} · generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="seccion">Datos del estudiante</div>
    <table>
        <tr><td class="etiqueta">Nombres y apellidos</td><td>{{ $estudiante->nombreCompleto() }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $estudiante->dni }}</td></tr>
        <tr><td class="etiqueta">Fecha de nacimiento</td><td>{{ $estudiante->fecha_nacimiento->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Estado</td><td>{{ $estudiante->estado->label() }}</td></tr>
    </table>

    <div class="seccion">Grados cursados</div>
    <table>
        <thead>
            <tr><th>Grado</th><th>Ciclo</th><th>Modalidad</th><th>Fecha de matrícula</th><th>Fin de estudios</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($matriculas as $matricula)
                <tr>
                    <td>{{ $matricula->grado->nombre }}</td>
                    <td>{{ $matricula->ciclo->nombre }}</td>
                    <td>{{ $matricula->ciclo->modalidad->label() }}</td>
                    <td>{{ $matricula->fecha_matricula->format('d/m/Y') }}</td>
                    <td>{{ $matricula->fecha_fin_estudio?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $matricula->estado->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin matrículas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion">Situación de pagos</div>
    <table>
        <tr><td class="etiqueta">Pagado</td><td>S/ {{ number_format($resumenPagos['totalPagado'], 2) }}</td></tr>
        <tr><td class="etiqueta">Pendiente</td><td>S/ {{ number_format($resumenPagos['totalPendiente'], 2) }}</td></tr>
        <tr><td class="etiqueta">Exonerado</td><td>S/ {{ number_format($resumenPagos['totalExonerado'], 2) }}</td></tr>
    </table>
    @if ($resumenPagos['cuotasVencidas']->isNotEmpty())
        <div class="subseccion">Cuotas vencidas</div>
        <table>
            <thead><tr><th>Cuota</th><th>Grado</th><th>Ciclo</th><th>Monto</th><th>Venció</th></tr></thead>
            <tbody>
                @foreach ($resumenPagos['cuotasVencidas'] as $cuota)
                    <tr>
                        <td>{{ $cuota->numero }}</td>
                        <td>{{ $cuota->planPago->matricula?->grado->nombre }}</td>
                        <td>{{ $cuota->planPago->matricula?->ciclo->nombre }}</td>
                        <td>S/ {{ number_format((float) $cuota->monto, 2) }}</td>
                        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($resumenPagos['cuotasPendientes']->isNotEmpty())
        <div class="subseccion">Cuotas pendientes</div>
        <table>
            <thead><tr><th>Cuota</th><th>Grado</th><th>Ciclo</th><th>Saldo</th><th>Vence</th></tr></thead>
            <tbody>
                @foreach ($resumenPagos['cuotasPendientes'] as $cuota)
                    <tr>
                        <td>{{ $cuota->numero }}</td>
                        <td>{{ $cuota->planPago->matricula?->grado->nombre }}</td>
                        <td>{{ $cuota->planPago->matricula?->ciclo->nombre }}</td>
                        <td>S/ {{ number_format($cuota->saldoPendiente(), 2) }}</td>
                        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="subseccion">Detalle de pagos</div>
    <table>
        <thead>
            <tr><th>Fecha</th><th>Concepto</th><th>Método</th><th>Estado</th><th>Monto</th></tr>
        </thead>
        <tbody>
            @forelse ($pagos as $pago)
                <tr>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</td>
                    <td>{{ $pago->metodo->label() }}</td>
                    <td>{{ $pago->estado->label() }}{{ $pago->estado->value === 'rechazado' && $pago->motivo_rechazo ? " — {$pago->motivo_rechazo}" : '' }}</td>
                    <td>S/ {{ number_format((float) $pago->monto, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin pagos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion">Documentos</div>
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
        <thead><tr><th>Ciclo</th><th>Generada</th><th>Entrega</th></tr></thead>
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

    <div class="seccion">Exámenes y notas</div>
    @if ($examenesUbicacion->isNotEmpty())
        <div class="subseccion">Exámenes de ubicación</div>
        <table>
            <thead><tr><th>Fecha</th><th>Costo</th><th>Resultado</th><th>Grado asignado</th></tr></thead>
            <tbody>
                @foreach ($examenesUbicacion as $examen)
                    <tr>
                        <td>{{ $examen->fecha->format('d/m/Y') }}</td>
                        <td>S/ {{ number_format((float) $examen->costo, 2) }}</td>
                        <td>{{ $examen->resultado ?? '—' }}</td>
                        <td>{{ $examen->gradoAsignado?->nombre ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @forelse ($notasPorCiclo as $entrada)
        <div class="subseccion">{{ $entrada['ciclo']->nombre }}</div>
        <table>
            <thead><tr><th>Curso</th><th>Promedio</th><th>Letra</th></tr></thead>
            <tbody>
                @foreach ($entrada['cursos'] as $curso)
                    <tr>
                        <td>{{ $curso['nombre'] }}</td>
                        <td>{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 1) : '—' }}</td>
                        <td>{{ $curso['letra'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Sin notas registradas todavía.</p>
    @endforelse

    <p class="codigo">Historial generado para el estudiante con DNI {{ $estudiante->dni }} · {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
