@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'right',
    'target' => null,
])

{{--
    Botones de la web pública. "primary" (turquesa) es el CTA habitual,
    "secondary" (borde azul) el alternativo, "accent" (naranja) se reserva
    para la acción más importante de la página y "ghost" es un enlace con
    ícono. Los fondos usan los tonos "deep" de la paleta para cumplir el
    contraste AA con texto blanco (ver :root --e360-* en app.css).
--}}
@php
    $variantes = [
        'primary' => 'bg-e360-primary-deep text-white shadow-sm shadow-e360-primary-deep/20 hover:bg-e360-primary-deeper',
        'secondary' => 'border border-e360-secondary bg-white text-e360-secondary-dark hover:bg-e360-secondary-tint',
        'accent' => 'bg-e360-accent-deep text-white shadow-sm shadow-e360-accent-deep/20 hover:bg-e360-accent-deeper',
        'ghost' => 'text-e360-primary-deep hover:bg-e360-primary-tint/70 hover:text-e360-primary-deeper',
    ];
    $tamanos = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-5 py-2.5 text-sm sm:px-6 sm:py-3 sm:text-base',
        'lg' => 'px-6 py-3 text-base sm:px-7 sm:py-3.5',
    ];
    $clases = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition duration-200 ease-out',
        'hover:-translate-y-px active:translate-y-0',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary',
        'disabled:pointer-events-none disabled:opacity-60',
        $tamanos[$size] ?? $tamanos['md'],
        $variantes[$variant] ?? $variantes['primary'],
    ]);
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        @if ($target) target="{{ $target }}" rel="noopener" @endif
        {{ $attributes->merge(['class' => $clases]) }}
    >
        @if ($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $clases]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
    </button>
@endif
