<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="{{ str_ends_with(\App\Shared\Support\Institucion::faviconUrl(), '.svg') ? 'image/svg+xml' : 'image/png' }}" href="{{ \App\Shared\Support\Institucion::faviconUrl() }}">

        @include('partials.theme-boot-script')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|quantico:400,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-bg font-sans text-ink antialiased">
        <div class="fondo-login" aria-hidden="true"></div>

        <a href="{{ route('landing') }}" wire:navigate class="fixed left-4 top-4 z-20 flex items-center gap-1.5 text-sm text-ink-faint transition hover:text-ink sm:left-8 sm:top-8">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            Volver al inicio
        </a>

        <div class="relative flex min-h-screen">
            {{--
                Panel de la mascota, solo en pantallas grandes -- en mobile
                no hay espacio para dos columnas y el saludo cede el lugar
                al formulario. Astronauta "de guardia" mientras se define la
                mascota oficial del login (ver conversación).
            --}}
            <div class="dashboard-hero-gradient relative hidden w-1/2 items-center justify-center overflow-hidden lg:flex">
                <div x-data="vortexDust()" class="absolute inset-0" aria-hidden="true"></div>

                <div class="hero-glow absolute h-72 w-72 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>

                <x-dashboard.mascot class="mascot-float relative h-72 w-72 -rotate-3 drop-shadow-2xl xl:h-80 xl:w-80" />

                <div class="absolute inset-x-0 bottom-14 px-10 text-center">
                    <p class="font-display text-xl font-medium text-white">¡Bienvenido a {{ config('institucion.nombre_corto') }}!</p>
                    <p class="mt-1 text-sm text-white/70">Tu espacio para aprender, crecer y avanzar.</p>
                </div>
            </div>

            <div class="flex w-full flex-col items-center justify-center px-4 py-10 lg:w-1/2">
                <div class="flex flex-col items-center">
                    <x-brand-logo size="lg" />
                    <p class="mt-3 font-sans text-base font-extrabold text-ink">{{ config('institucion.nombre') }}</p>
                    <p class="text-xs text-ink-faint">Formación y capacitación · Modalidad virtual</p>
                </div>

                <div class="mt-8 w-full overflow-hidden rounded-2xl border border-border bg-surface px-6 py-6 shadow-2xl shadow-black/20 sm:max-w-md sm:px-8 sm:py-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
