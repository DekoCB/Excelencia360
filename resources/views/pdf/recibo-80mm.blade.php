<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6px 8px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; color: #1B1F27; width: 100%; }

        .centro { text-align: center; }
        .colegio-nombre { font-size: 11px; font-weight: bold; margin: 0; }
        .colegio-subtitulo { font-size: 7.5px; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 1px dashed #5B6472; margin: 6px 0; }

        .titulo-recibo h1 { font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; margin: 0; }
        .titulo-recibo p { margin: 2px 0 0; font-size: 9px; }

        table.datos { width: 100%; border-collapse: collapse; margin: 6px 0; }
        table.datos td { padding: 1px 0; font-size: 8.5px; vertical-align: top; }
        table.datos .etiqueta { width: 34%; color: #5B6472; }

        table.conceptos { width: 100%; border-collapse: collapse; margin: 6px 0; }
        table.conceptos th { text-align: left; border-bottom: 1px solid #1B1F27; padding: 2px 0; font-size: 8px; text-transform: uppercase; }
        table.conceptos td { padding: 3px 0; font-size: 8.5px; }
        table.conceptos .col-monto { text-align: right; white-space: nowrap; }

        table.total { width: 100%; border-collapse: collapse; margin-top: 4px; border-top: 1px solid #1B1F27; }
        table.total td { padding: 3px 0; font-size: 9.5px; font-weight: bold; }
        table.total .col-monto { text-align: right; }

        .codigo { margin-top: 10px; font-size: 7px; color: #5B6472; text-align: center; }
    </style>
</head>
<body>
    <div class="centro">
        <p class="colegio-nombre">GRUPO EXCELENCIA 360</p>
        <p class="colegio-subtitulo">{{ config('institucion.actividad') }}<br>RUC {{ config('institucion.ruc') }}</p>
    </div>
    <hr class="regla">

    <div class="titulo-recibo centro">
        <h1>{{ $serie->titulo() }}</h1>
        <p>N.° {{ $serie->numeroCompleto($recibo->numero_recibo) }}</p>
    </div>
    <hr class="regla">

    <table class="datos">
        <tr><td class="etiqueta">Fecha de pago</td><td>{{ $pago->fecha_pago->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Alumno(a)</td><td>{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $pago->estudiante?->dni ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Medio de pago</td><td>{{ $pago->medioPagoResumen() }}</td></tr>
    </table>

    <table class="conceptos">
        <thead>
            <tr><th>Concepto</th><th class="col-monto">Monto</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $pago->nombreConcepto() }}{{ $pago->detalle ? ' — '.$pago->detalle : '' }}</td>
                <td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="total">
        <tr><td>TOTAL</td><td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td></tr>
    </table>

    @if ($pago->cuota)
        <p style="font-size: 8px; margin-top: 4px;">
            Cuota {{ $pago->cuota->numero }} de {{ $pago->cuota->planPago->numero_cuotas }}
            ({{ $pago->cuota->saldoPendiente() <= 0.0 ? 'Completo' : 'Saldo: S/ '.number_format($pago->cuota->saldoPendiente(), 2) }})
        </p>
    @endif

    @if ($pago->observacion)
        <p style="font-size: 8px; margin-top: 4px;">Obs: {{ $pago->observacion }}</p>
    @endif

    <hr class="regla">
    <p class="codigo">Emitido {{ $recibo->emitido_en->format('d/m/Y H:i') }}</p>
</body>
</html>
