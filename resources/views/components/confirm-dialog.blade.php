{{--
    Diálogo de confirmación único para toda la app: reemplaza wire:confirm
    (que dispara el confirm() nativo del navegador, sin estilo propio) por
    uno propio. Se monta una sola vez en el layout; cada botón lo dispara
    con $store.confirm.preguntar(mensaje, accion, opciones) en vez de
    wire:confirm -- ver resources/js/app.js para el store.
--}}
<div
    x-data
    x-show="$store.confirm.open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
    x-on:keydown.escape.window="$store.confirm.cancelar()"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        x-show="$store.confirm.open"
        x-on:click.outside="$store.confirm.cancelar()"
        class="w-full max-w-sm rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <p class="text-sm text-ink" x-text="$store.confirm.mensaje"></p>

        <div class="mt-5 flex justify-end gap-3">
            <x-secondary-button type="button" x-on:click="$store.confirm.cancelar()">Cancelar</x-secondary-button>
            <button
                type="button"
                x-on:click="$store.confirm.confirmar()"
                x-text="$store.confirm.etiquetaConfirmar"
                class="inline-flex items-center rounded-md px-4 py-2 font-display text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                x-bind:class="$store.confirm.peligro ? 'bg-danger focus:ring-danger' : 'bg-accent focus:ring-accent'"
            ></button>
        </div>
    </div>
</div>
