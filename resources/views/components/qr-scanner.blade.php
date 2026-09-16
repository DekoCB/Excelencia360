@props(['metodo'])

{{--
    Lector de QR por cámara (ver resources/js/app.js, Alpine.data('lectorQr')
    y la librería jsQR). $metodo es el nombre del método Livewire del
    componente padre que recibe el texto decodificado como único argumento
    -- así este componente es genérico y sirve tanto para escanear el QR
    de un estudiante (asistencia) como el de un docente (asistencia de
    docentes) sin duplicar la lógica de cámara/decodificación.
--}}
<div x-data="lectorQr('{{ $metodo }}')" x-on:beforeunload.window="detener()">
    <div class="flex flex-wrap items-center gap-2">
        <x-secondary-button type="button" x-show="! activo" x-on:click="iniciar()">
            <x-heroicon-o-qr-code class="mr-1.5 h-4 w-4" />
            Escanear QR
        </x-secondary-button>
        <x-secondary-button type="button" x-show="activo" x-cloak x-on:click="detener()">
            Detener cámara
        </x-secondary-button>
        <span x-show="activo" x-cloak class="text-xs text-ink-faint">Apunta la cámara al código del carnet.</span>
    </div>

    <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-danger"></p>

    <div x-show="activo" x-cloak class="relative mt-3 max-w-xs overflow-hidden rounded-xl border border-border bg-ink">
        <video x-ref="video" muted playsinline class="w-full"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>
    </div>
</div>
