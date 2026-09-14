{{--
    Barra de navegación pública: fija arriba, blanca, con sombra sutil solo
    cuando hay scroll. Los enlaces apuntan a los anclajes de la portada con
    URL absoluta para que también funcionen desde /cursos/{slug}.
--}}
@php
    $inicio = route('landing');
    $enlaces = [
        ['href' => $inicio, 'etiqueta' => 'Inicio'],
        ['href' => $inicio.'#conocenos', 'etiqueta' => 'Conócenos'],
        ['href' => $inicio.'#cursos', 'etiqueta' => 'Cursos'],
        ['href' => $inicio.'#servicios', 'etiqueta' => 'Servicios'],
        ['href' => $inicio.'#blog', 'etiqueta' => 'Blog'],
        ['href' => $inicio.'#contacto', 'etiqueta' => 'Contáctanos'],
    ];
    $claseEnlace = 'rounded-md px-3 py-2 text-sm font-medium text-e360-text transition-colors hover:bg-e360-primary-tint/60 hover:text-e360-primary-deep focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary';
@endphp

<header
    x-data="{ menuAbierto: false, conScroll: false }"
    x-init="conScroll = window.scrollY > 8"
    x-on:scroll.window.passive="conScroll = window.scrollY > 8"
    x-on:keydown.escape.window="menuAbierto = false"
    x-on:click.outside="menuAbierto = false"
    :class="conScroll ? 'shadow-[0_1px_0_0_rgb(var(--e360-border)),0_10px_30px_-16px_rgb(var(--e360-heading)/0.18)]' : ''"
    class="sticky top-0 z-50 bg-white/95 backdrop-blur transition-shadow duration-300"
>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-[72px] lg:px-8">
        <a
            href="{{ $inicio }}"
            class="shrink-0 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-e360-secondary"
            aria-label="{{ config('institucion.nombre') }}, ir al inicio"
        >
            <x-brand-logo size="md" />
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Principal">
            @foreach ($enlaces as $enlace)
                <a href="{{ $enlace['href'] }}" class="{{ $claseEnlace }}">{{ $enlace['etiqueta'] }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <a
                href="{{ route('login') }}"
                wire:navigate
                class="rounded-md text-sm font-medium text-e360-muted transition-colors hover:text-e360-heading focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-e360-secondary"
            >
                Iniciar sesión
            </a>
            <x-landing.cta-button href="{{ $inicio }}#cursos" size="sm">Ver cursos</x-landing.cta-button>
        </div>

        <button
            type="button"
            x-on:click="menuAbierto = ! menuAbierto"
            :aria-expanded="menuAbierto.toString()"
            aria-controls="menu-movil"
            class="-mr-2 inline-flex h-11 w-11 items-center justify-center rounded-lg text-e360-heading transition hover:bg-e360-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary lg:hidden"
        >
            <span class="sr-only" x-text="menuAbierto ? 'Cerrar menú' : 'Abrir menú'">Abrir menú</span>
            <x-heroicon-o-bars-3 x-show="! menuAbierto" class="h-6 w-6" aria-hidden="true" />
            <x-heroicon-o-x-mark x-show="menuAbierto" x-cloak class="h-6 w-6" aria-hidden="true" />
        </button>
    </div>

    <div
        id="menu-movil"
        x-show="menuAbierto"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="-translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="-translate-y-2 opacity-0"
        class="border-t border-e360-border bg-white px-4 pb-5 pt-2 shadow-[0_20px_40px_-20px_rgb(var(--e360-heading)/0.25)] sm:px-6 lg:hidden"
    >
        <nav class="flex flex-col gap-1" aria-label="Principal">
            @foreach ($enlaces as $enlace)
                <a href="{{ $enlace['href'] }}" x-on:click="menuAbierto = false" class="{{ $claseEnlace }} py-2.5">{{ $enlace['etiqueta'] }}</a>
            @endforeach
        </nav>
        <div class="mt-3 flex flex-col gap-3 border-t border-e360-border pt-4">
            <x-landing.cta-button href="{{ $inicio }}#cursos" x-on:click="menuAbierto = false" class="w-full">Ver cursos</x-landing.cta-button>
            <a href="{{ route('login') }}" wire:navigate class="{{ $claseEnlace }} text-center text-e360-muted">Iniciar sesión</a>
        </div>
    </div>
</header>
