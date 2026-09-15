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
    <body class="bg-black font-sans text-ink antialiased">
        {{--
            Fondo de video a pantalla completa, solo en esta pantalla de login
            (layout aparte de layouts/guest.blade.php a propósito -- ese sigue
            usándose tal cual, con su vortex de estrellas, en 2FA/recuperar
            contraseña/verificar certificado). object-cover para que cubra
            todo el viewport sin importar el aspecto de pantalla, recortando
            en vez de dejar bandas.
        --}}
        <video
            class="js-fondo-video fixed inset-0 h-full w-full object-cover"
            autoplay
            muted
            loop
            playsinline
            aria-hidden="true"
        >
            <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260815_034306_229eccbe-fd8f-40fb-8002-9868ef2bb1a8.mp4" type="video/mp4">
        </video>
        <div class="fixed inset-0 bg-black/45" aria-hidden="true"></div>

        <a href="{{ route('landing') }}" wire:navigate class="fixed left-4 top-4 z-20 flex items-center gap-1.5 text-sm text-white/80 transition hover:text-white sm:left-8 sm:top-8">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            Volver al inicio
        </a>

        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div class="flex flex-col items-center">
                <x-brand-logo variant="mark" size="lg" class="relative -top-6 drop-shadow-[0_6px_18px_rgba(0,0,0,0.45)]" />
                {{-- relative -top-* en vez de margin: sube el escudo y el
                     texto sin mover al hermano de abajo (la tarjeta), ya que
                     el desplazamiento relativo no afecta el flujo del
                     documento. --}}
                <div class="relative -top-9 text-center">
                    <p class="mt-3 font-sans text-base font-extrabold text-white">{{ config('institucion.nombre') }}</p>
                    <p class="text-xs text-white/70">Formación y capacitación · Modalidad virtual</p>
                </div>
            </div>

            <div class="relative mt-8 w-full sm:max-w-md">
                <div class="w-full overflow-hidden rounded-2xl border border-border bg-surface px-6 py-6 shadow-2xl shadow-black/40 sm:px-8 sm:py-8">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <script>
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.querySelectorAll('.js-fondo-video').forEach((video) => video.pause());
            }
        </script>
    </body>
</html>
