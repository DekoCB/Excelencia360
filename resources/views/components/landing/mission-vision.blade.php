@props(['kind' => 'mision', 'title'])

{{--
    Tarjeta de misión (turquesa) o visión (azul): filete de color al borde
    izquierdo, ícono y el texto oficial entre comillas, sin reformular.
--}}
@php
    $estilos = [
        'mision' => ['borde' => 'border-l-e360-primary', 'tono' => 'primary', 'icono' => 'flag'],
        'vision' => ['borde' => 'border-l-e360-secondary', 'tono' => 'secondary', 'icono' => 'eye'],
    ];
    $estilo = $estilos[$kind] ?? $estilos['mision'];
@endphp

<article {{ $attributes->class(['flex h-full flex-col rounded-2xl border border-e360-border border-l-4 bg-white p-6 shadow-sm shadow-e360-heading/5 sm:p-8', $estilo['borde']]) }}>
    <div class="flex items-center gap-4">
        <x-landing.icon-circle :icon="$estilo['icono']" :tone="$estilo['tono']" />
        <h3 class="font-brand text-2xl font-extrabold text-e360-heading">{{ $title }}</h3>
    </div>
    <blockquote class="mt-5 text-base leading-relaxed text-e360-text sm:text-lg">
        <p>“{{ $slot }}”</p>
    </blockquote>
</article>
