<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.estilos-base')
    </style>
</head>
<body>
    @include('pdf.partials.encabezado', ['titulo' => 'Historial del estudiante', 'subtitulo' => config('institucion.nombre').' · generado el '.now()->format('d/m/Y H:i')])

    <div class="seccion-titulo">Datos del estudiante</div>
    <table class="datos">
        <tr><td class="etiqueta">Nombres y apellidos</td><td class="valor">{{ $estudiante->nombreCompleto() }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td class="valor">{{ $estudiante->dni }}</td></tr>
        <tr><td class="etiqueta">Fecha de nacimiento</td><td class="valor">{{ $estudiante->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Estado</td><td class="valor">{{ $estudiante->estado->label() }}</td></tr>
    </table>

    <div class="seccion-titulo">Semestres cursados</div>
    <table class="tabla-pdf">
        <thead>
            <tr><th>Semestre</th><th>Periodo académico</th><th>Fecha de matrícula</th><th>Fin de estudios</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($matriculas as $matricula)
                <tr>
                    <td>{{ $matricula->grado->nombre }}</td>
                    <td>{{ $matricula->ciclo->nombre }}</td>
                    <td>{{ $matricula->fecha_matricula->format('d/m/Y') }}</td>
                    <td>{{ $matricula->fecha_fin_estudio?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $matricula->estado->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="sin-datos">Sin matrículas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">Situación de pagos</div>
    <table class="datos">
        <tr><td class="etiqueta">Pagado</td><td class="valor">S/ {{ number_format($resumenPagos['totalPagado'], 2) }}</td></tr>
        <tr><td class="etiqueta">Pendiente</td><td class="valor">S/ {{ number_format($resumenPagos['totalPendiente'], 2) }}</td></tr>
        <tr><td class="etiqueta">Exonerado</td><td class="valor">S/ {{ number_format($resumenPagos['totalExonerado'], 2) }}</td></tr>
    </table>
    @if ($resumenPagos['cuotasVencidas']->isNotEmpty())
        <div class="subseccion">Cuotas vencidas</div>
        <table class="tabla-pdf">
            <thead><tr><th>Cuota</th><th>Semestre</th><th>Período de matrícula</th><th>Monto</th><th>Venció</th></tr></thead>
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
        <table class="tabla-pdf">
            <thead><tr><th>Cuota</th><th>Semestre</th><th>Período de matrícula</th><th>Saldo</th><th>Vence</th></tr></thead>
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
    <table class="tabla-pdf">
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
                <tr><td colspan="5" class="sin-datos">Sin pagos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">Documentos</div>
    <div class="subseccion">Subidos al matricularse</div>
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
    <div class="subseccion">Emitidos por la institución</div>
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
    <div class="subseccion">Libretas generadas</div>
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

    <div class="seccion-titulo">Exámenes y notas</div>
    @if ($examenesUbicacion->isNotEmpty())
        <div class="subseccion">Exámenes de ubicación</div>
        <table class="tabla-pdf">
            <thead><tr><th>Fecha</th><th>Costo</th><th>Resultado</th><th>Semestre asignado</th></tr></thead>
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
        <table class="tabla-pdf">
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
        <p class="sin-datos">Sin notas registradas todavía.</p>
    @endforelse

    <p class="pie-generado">Historial generado para el estudiante con DNI {{ $estudiante->dni }} · {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
