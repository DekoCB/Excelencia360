@props(['variant' => 'full', 'size' => 'md'])

{{--
    Marca institucional, en 3 variantes:

    - "icon": solo el emblema (gorro, rayos y libro), recortado del logo
      real -- para espacios angostos y cuadrados (sidebar, favicon-like).
    - "full" (por defecto): el mismo recorte del emblema + el nombre
      escrito a un lado -- para navbar/footer, donde el logo entero
      (vertical, con el wordmark "EXCELENCIA 360" incluido abajo) queda
      demasiado angosto y su texto ilegible a esa altura.
    - "mark": el archivo del logo completo, tal cual, sin recortar --
      para los pocos lugares con espacio vertical de sobra para lucirlo
      (login, panel de 2FA/recuperar contraseña).

    El recorte de "icon"/"full" es puro CSS (object-fit + object-position)
    sobre el archivo real: no se toca el PNG ni se alteran sus proporciones
    o colores, solo se le abre una ventana cuadrada que muestra nada más
    el emblema. Mientras el logo no exista, cada variante cae a un
    reemplazo tipográfico para que ninguna pantalla enlace a una imagen
    rota.
--}}
@php
    $logoUrl = \App\Shared\Support\Institucion::logoUrl();
    $nombre = \App\Shared\Support\Institucion::nombre();

    // Proporción del emblema dentro del archivo del logo (950×1510): ocupa
    // el 78% superior del alto: el resto es el wordmark. object-position
    // top + esta relación de aspecto recortan justo ahí.
    $aspectoEmblema = '95 / 118';

    $alturaIcono = ['sm' => 'h-8', 'md' => 'h-10', 'lg' => 'h-14'][$size] ?? 'h-10';
    $alturaMarca = ['sm' => 'h-14', 'md' => 'h-20', 'lg' => 'h-28'][$size] ?? 'h-20';
    $tamanoTexto = ['sm' => 'text-base', 'md' => 'text-xl', 'lg' => 'text-3xl'][$size] ?? 'text-xl';
    $tamanoCuadro = ['sm' => 'h-8 w-8 text-[11px]', 'md' => 'h-10 w-10 text-xs', 'lg' => 'h-14 w-14 text-base'][$size] ?? 'h-10 w-10 text-xs';
@endphp

@if ($variant === 'mark')
    <span {{ $attributes->class(['inline-flex items-center']) }}>
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $nombre }}" class="{{ $alturaMarca }} w-auto shrink-0 object-contain">
        @else
            <span class="font-brand font-extrabold uppercase leading-none tracking-tight text-e360-secondary-dark {{ $tamanoTexto }}">
                <span class="sr-only">{{ $nombre }}</span>
                <span aria-hidden="true">Excelencia <span class="text-e360-primary-deep">360</span></span>
            </span>
        @endif
    </span>
@elseif ($variant === 'icon')
    <span {{ $attributes->class(['inline-flex shrink-0 items-center']) }}>
        @if ($logoUrl)
            <span class="{{ $alturaIcono }} shrink-0 overflow-hidden rounded-lg" style="aspect-ratio: {{ $aspectoEmblema }}">
                <img src="{{ $logoUrl }}" alt="{{ $nombre }}" class="h-full w-full object-cover object-top">
            </span>
        @else
            <span class="flex shrink-0 items-center justify-center rounded-lg bg-e360-primary-deep font-brand font-extrabold text-white {{ $tamanoCuadro }}" role="img" aria-label="{{ $nombre }}">360</span>
        @endif
    </span>
@else
    <span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
        @if ($logoUrl)
            <span class="{{ $alturaIcono }} shrink-0 overflow-hidden rounded-lg" style="aspect-ratio: {{ $aspectoEmblema }}" aria-hidden="true">
                <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover object-top">
            </span>
            <span class="sr-only">{{ $nombre }}</span>
            <span aria-hidden="true" class="font-brand font-extrabold uppercase leading-none tracking-tight text-e360-secondary-dark {{ $tamanoTexto }}">
                Excelencia <span class="text-e360-primary-deep">360</span>
            </span>
        @else
            <span class="font-brand font-extrabold uppercase leading-none tracking-tight text-e360-secondary-dark {{ $tamanoTexto }}">
                <span class="sr-only">{{ $nombre }}</span>
                <span aria-hidden="true">Excelencia <span class="text-e360-primary-deep">360</span></span>
            </span>
        @endif
    </span>
@endif
