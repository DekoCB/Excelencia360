{{--
    Encabezado institucional (logo + nombre + regla de color), compartido
    por todos los PDFs -- ver estilos-base.blade.php para las clases que
    usa. $titulo y $subtitulo son opcionales: cuando se pasan, agregan el
    h1 centrado debajo de la regla (patrón usado por reporte.blade.php,
    flujo-caja.blade.php, historial-*.blade.php, etc.).
--}}
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

@isset($titulo)
    <h1 class="titulo-pdf">{{ $titulo }}</h1>
@endisset
@isset($subtitulo)
    <p class="subtitulo-pdf">{{ $subtitulo }}</p>
@endisset
