@props(['variant' => 'full', 'size' => 'md'])

{{--
    Marca institucional. Muestra el logo de config/institucion.php si el
    archivo ya existe; si no, una marca tipográfica provisional (o un
    cuadro "360" en la variante "icon", para el sidebar colapsado), de modo
    que ninguna pantalla enlace a una imagen rota mientras llega el logo.
--}}
@php
    $logoUrl = \App\Shared\Support\Institucion::logoUrl();
    $nombre = \App\Shared\Support\Institucion::nombre();

    $alturaImagen = ['sm' => 'h-8', 'md' => 'h-10', 'lg' => 'h-14'][$size] ?? 'h-10';
    $tamanoTexto = ['sm' => 'text-base', 'md' => 'text-xl', 'lg' => 'text-3xl'][$size] ?? 'text-xl';
    $tamanoCuadro = ['sm' => 'h-8 w-8 text-[11px]', 'md' => 'h-10 w-10 text-xs', 'lg' => 'h-14 w-14 text-base'][$size] ?? 'h-10 w-10 text-xs';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $nombre }}" class="{{ $alturaImagen }} w-auto shrink-0 object-contain">
    @elseif ($variant === 'icon')
        <span class="flex shrink-0 items-center justify-center rounded-lg bg-e360-primary-deep font-brand font-extrabold text-white {{ $tamanoCuadro }}" role="img" aria-label="{{ $nombre }}">360</span>
    @else
        <span class="font-brand font-extrabold uppercase leading-none tracking-tight text-e360-secondary-dark {{ $tamanoTexto }}">
            <span class="sr-only">{{ $nombre }}</span>
            <span aria-hidden="true">Excelencia <span class="text-e360-primary-deep">360</span></span>
        </span>
    @endif
</span>
