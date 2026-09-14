<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1B1F27; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.encabezado td { vertical-align: middle; }
        .logo-celda { width: 78px; padding-right: 14px; text-align: center; }
        .logo-celda img { width: 70px; }
        .centro-costo { margin: 2px 0 0; font-size: 9px; font-weight: bold; letter-spacing: 0.06em; color: #12225C; }
        .colegio-nombre { font-size: 16px; font-weight: bold; color: #12225C; margin: 0; line-height: 1.15; }
        .colegio-nombre span { display: block; font-size: 22px; }
        .colegio-subtitulo { font-size: 9.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 2px solid #12225C; margin: 6px 0 16px; }

        .titulo-recibo { text-align: center; margin-bottom: 18px; }
        .titulo-recibo h1 { font-size: 16px; letter-spacing: 0.05em; text-transform: uppercase; color: #12225C; margin: 0; }
        .titulo-recibo p { margin: 2px 0 0; font-size: 11px; color: #5B6472; }

        table.fechas { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.fechas td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        table.fechas .etiqueta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin: 0 0 4px; }
        table.caja { width: 100%; border-collapse: collapse; }
        table.caja th, table.caja td { border: 1px solid #C7CEDB; text-align: center; padding: 5px 4px; font-size: 10px; }
        table.caja th { background: #F0F2F5; color: #5B6472; font-weight: normal; text-transform: uppercase; font-size: 8px; }

        table.alumno { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.alumno td { padding: 4px 0; font-size: 11px; }
        table.alumno .etiqueta { color: #5B6472; width: 18%; text-transform: uppercase; letter-spacing: 0.03em; font-size: 9px; }
        table.alumno .valor { font-weight: bold; border-bottom: 1px solid #C7CEDB; padding-bottom: 3px; }

        table.conceptos { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.conceptos th { background: #F0F2F5; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border: 1px solid #C7CEDB; }
        table.conceptos td { padding: 8px; border: 1px solid #C7CEDB; }
        table.conceptos .col-cant { width: 10%; text-align: center; }
        table.conceptos .col-monto { width: 18%; text-align: right; white-space: nowrap; }

        .observacion { margin-bottom: 16px; }
        .observacion .etiqueta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin: 0 0 4px; }
        .observacion .caja-texto { border: 1px solid #C7CEDB; border-radius: 3px; min-height: 28px; padding: 6px 8px; font-size: 10.5px; }

        table.pie { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.pie td { width: 33.33%; padding: 4px 0; font-size: 10.5px; }
        table.pie .etiqueta { color: #5B6472; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }

        .codigo { margin-top: 30px; font-size: 9px; color: #8891A0; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.cuerpo-recibo', ['serie' => $serie])
</body>
</html>
