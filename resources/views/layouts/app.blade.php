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
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|quantico:400,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-shell font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="flex h-dvh overflow-hidden bg-bg">
            <!-- Sidebar (desktop) -->
            <aside class="sidebar-desktop relative hidden w-72 shrink-0 flex-col border-r border-border bg-surface transition-all duration-300 ease-in-out md:flex">
                <div class="flex h-16 items-center border-b border-border px-4">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo />
                    </a>
                </div>
                <x-sidebar-nav />

                <button
                    type="button"
                    x-data
                    @click="
                        const colapsado = document.documentElement.classList.toggle('sidebar-collapsed');
                        localStorage.setItem('ceba-sidebar-collapsed', colapsado ? '1' : '0');
                    "
                    class="absolute -right-3 top-[4.25rem] z-10 flex h-6 w-6 items-center justify-center rounded-full border border-border bg-surface text-ink-faint shadow-sm transition hover:text-ink"
                    aria-label="Colapsar o expandir el menú"
                >
                    <x-heroicon-o-chevron-left class="sidebar-chevron h-3.5 w-3.5 transition-transform duration-300" />
                </button>
            </aside>

            <!-- Sidebar (mobile off-canvas) -->
            <div
                x-show="sidebarOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-ink/40 md:hidden"
                @click="sidebarOpen = false"
                x-transition.opacity
            ></div>
            <aside
                x-show="sidebarOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-surface md:hidden"
            >
                <div class="flex h-16 items-center justify-between border-b border-border px-4">
                    <x-application-logo />
                    <button @click="sidebarOpen = false" class="rounded-md p-2 text-ink-faint hover:bg-surface-2">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <x-sidebar-nav />
            </aside>

            <!-- Content column -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <livewire:layout.navigation />

                @if (isset($header))
                    <header class="px-4 pb-2 pt-6 sm:px-6">
                        {{ $header }}
                    </header>
                @endif

                <main class="relative flex-1 overflow-hidden">
                    <div class="ambient-glow" aria-hidden="true"></div>

                    <div class="relative z-10 h-full overflow-y-auto p-4 sm:p-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-confirm-dialog />
    </body>
</html>
