{{--
    Estilos base compartidos por todos los PDFs del sistema -- encabezado
    institucional (logo + nombre + regla de color) y la paleta ya usada en
    certificado.blade.php/libreta.blade.php, para que cualquier PDF nuevo
    o rediseñado se vea como parte del mismo sistema en vez de una tabla
    genérica. Se incluye dentro del <style> de cada vista con @include.
--}}
body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1B1F27; }

table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
table.encabezado td { vertical-align: middle; }
.logo-celda { width: 64px; padding-right: 14px; }
.logo-celda img { width: 56px; }
.institucion { font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; color: #165D96; margin: 0 0 2px; }
.colegio-nombre { font-size: 18px; font-weight: bold; color: #0F7A78; margin: 0; line-height: 1.15; }
.colegio-nombre span { display: block; font-size: 25px; color: #18BEBC; }
.colegio-subtitulo { font-size: 10.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

hr.regla { border: none; border-top: 2px solid #18BEBC; margin: 8px 0 18px; }

h1.titulo-pdf { text-align: center; font-size: 17px; margin: 0 0 4px; letter-spacing: 0.04em; text-transform: uppercase; color: #0F7A78; }
p.subtitulo-pdf { text-align: center; color: #5B6472; margin: 0 0 18px; font-size: 10px; }

table.tabla-pdf { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
table.tabla-pdf th { background: #165D96; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #fff; }
table.tabla-pdf td { padding: 6px 8px; border-bottom: 1px solid #EEF0F3; font-size: 10.5px; }
table.tabla-pdf tbody tr:nth-child(even) { background: #F8FAFB; }
.monto-col { text-align: right; white-space: nowrap; }
.sin-datos { color: #8891A0; font-style: italic; text-align: center; padding: 14px; }

.etiqueta { color: #165D96; text-transform: uppercase; letter-spacing: 0.03em; font-size: 9.5px; }
.seccion-titulo { background: #18BEBC; color: #fff; font-size: 11px; font-weight: bold; letter-spacing: 0.03em; text-transform: uppercase; padding: 6px 10px; border-radius: 4px 4px 0 0; margin-top: 16px; }
.subseccion { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #165D96; margin-top: 10px; margin-bottom: 4px; }

table.datos { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #F3FBFB; border: 1px solid #DBDFE6; border-radius: 4px; }
table.datos td { padding: 5px 10px; font-size: 11px; border-bottom: none; }
table.datos td.etiqueta { width: 22%; }
table.datos td.valor { font-weight: bold; color: #1B1F27; }

p.pie-generado { margin-top: 18px; font-size: 9.5px; color: #8891A0; }

/* Documentos tipo carta (constancias cortas) -- mismo patrón de firma que
   certificado.blade.php, reusado acá en vez de duplicarlo. */
p.cuerpo-carta { text-align: justify; line-height: 1.6; font-size: 12.5px; margin: 0 0 14px; }
.firma-carta { margin-top: 56px; text-align: center; }
.firma-carta .linea { border-top: 1px solid #1B1F27; width: 220px; margin: 0 auto; }
.firma-carta p { margin: 4px 0 0; text-align: center; font-size: 11px; }
.firma-carta .nombre { font-weight: bold; }
