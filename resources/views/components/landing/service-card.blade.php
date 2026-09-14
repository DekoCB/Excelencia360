@props(['servicio', 'featured' => false])

{{--
    Tarjeta de servicio. La destacada (Capacitación en General, el servicio
    central de la institución) ocupa dos columnas y lleva fondo turquesa
    suave; el resto van en blanco. El CTA lleva al formulario con el asunto
    ya elegido (ver el listener data-asunto en landing/index).
--}}
@php
    $asunto = 'Servicio: '.$servicio['nombre'];
    $enlaceContacto = route('landing', ['asunto' => $asunto]).'#contacto';
@endphp

<article {{ $attributes->class([
    'group flex h-full flex-col rounded-2xl border p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-e360-heading/5 sm:p-7',
    'border-e360-primary/30 bg-e360-primary-tint hover:border-e360-primary/60' => $featured,
    'border-e360-border bg-white hover:border-e360-primary/40' => ! $featured,
]) }}>
    <x-landing.icon-circle :icon="$servicio['icono']" :tone="$featured ? 'primary' : 'secondary'" :interactive="true" />
    <h3 class="mt-5 font-brand text-lg font-bold text-e360-heading sm:text-xl">{{ $servicio['nombre'] }}</h3>
    <p class="mt-2 text-sm leading-relaxed text-e360-text">{{ $servicio['descripcion'] }}</p>
    <div class="mt-auto pt-6">
        <a
            href="{{ $enlaceContacto }}"
            data-asunto="{{ $asunto }}"
            class="inline-flex items-center gap-1.5 rounded-md text-sm font-semibold text-e360-primary-deep transition-colors hover:text-e360-primary-deeper focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary"
        >
            Solicitar información
            <x-heroicon-o-arrow-right class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
        </a>
    </div>
</article>
