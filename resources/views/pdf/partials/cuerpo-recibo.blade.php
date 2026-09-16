{{-- @var \App\Modules\Pagos\Enums\SerieReciboEnum $serie --}}
<table class="encabezado">
    <tr>
        @if ($logoInstitucion = \App\Shared\Support\Institucion::emblemaPath())
            <td class="logo-celda">
                <img src="{{ $logoInstitucion }}" alt="{{ config('institucion.nombre') }}">
            </td>
        @endif
        <td>
            <p class="colegio-nombre">GRUPO<span>EXCELENCIA 360</span></p>
            <p class="colegio-subtitulo">{{ config('institucion.actividad') }} · RUC {{ config('institucion.ruc') }} · {{ \App\Shared\Support\Institucion::direccionCompleta() }}</p>
        </td>
    </tr>
</table>
<hr class="regla">

<div class="titulo-recibo">
    <h1>{{ $serie->titulo() }}</h1>
    <p>N.° {{ $serie->numeroCompleto($recibo->numero_recibo) }}</p>
</div>

<table class="fechas">
    <tr>
        <td>
            <p class="etiqueta">Fecha de emisión</p>
            <table class="caja">
                <tr><th>Día</th><th>Mes</th><th>Año</th></tr>
                <tr>
                    <td>{{ $recibo->emitido_en->format('d') }}</td>
                    <td>{{ $recibo->emitido_en->format('m') }}</td>
                    <td>{{ $recibo->emitido_en->format('Y') }}</td>
                </tr>
            </table>
        </td>
        <td>
            <p class="etiqueta">Fecha de pago</p>
            <table class="caja">
                <tr><th>Día</th><th>Mes</th><th>Año</th></tr>
                <tr>
                    <td>{{ $pago->fecha_pago->format('d') }}</td>
                    <td>{{ $pago->fecha_pago->format('m') }}</td>
                    <td>{{ $pago->fecha_pago->format('Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="alumno">
    <tr><td class="etiqueta">Alumno(a)</td><td class="valor">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</td></tr>
    <tr><td class="etiqueta">DNI</td><td class="valor">{{ $pago->estudiante?->dni ?? '—' }}</td></tr>
</table>

<table class="conceptos">
    <thead>
        <tr>
            <th class="col-cant">Cant.</th>
            <th>Descripción</th>
            <th class="col-monto">P. Unit.</th>
            <th class="col-monto">Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-cant">1</td>
            <td>{{ $pago->nombreConcepto() }}{{ $pago->detalle ? ' — '.$pago->detalle : '' }}</td>
            <td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
            <td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
        </tr>
    </tbody>
</table>

@if ($pago->partes->count() > 1)
    <table class="conceptos">
        <thead>
            <tr><th colspan="2">Partes del pago</th></tr>
        </thead>
        <tbody>
            @foreach ($pago->partes as $parte)
                <tr>
                    <td>{{ $parte->metodoConNota() }}</td>
                    <td class="col-monto">S/ {{ number_format((float) $parte->monto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="observacion">
    <p class="etiqueta">Observación</p>
    <div class="caja-texto">{{ $pago->observacion ?: '—' }}</div>
</div>

<table class="pie">
    <tr>
        <td>
            <p class="etiqueta">Cuota</p>
            @if ($pago->cuota)
                N.° {{ $pago->cuota->numero }} de {{ $pago->cuota->planPago->numero_cuotas }}
                ({{ $pago->cuota->saldoPendiente() <= 0.0 ? 'Completo' : 'Parcial' }})
                @if ($pago->cuota->saldoPendiente() > 0.0)
                    <br>Saldo: S/ {{ number_format($pago->cuota->saldoPendiente(), 2) }}
                @endif
            @else
                —
            @endif
        </td>
        <td>
            <p class="etiqueta">Programa de estudio</p>
            {{ $pago->cuota?->planPago?->matricula?->ciclo?->nombre ?? '—' }}
        </td>
        <td>
            <p class="etiqueta">Medio de pago</p>
            {{ $pago->medioPagoResumen() }}
        </td>
    </tr>
</table>

<p class="codigo">{{ $serie->titulo() }} N.° {{ $serie->numeroCompleto($recibo->numero_recibo) }} · emitido el {{ $recibo->emitido_en->format('d/m/Y H:i') }}</p>
