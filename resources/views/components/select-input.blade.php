@props(['options' => [], 'placeholder' => 'Selecciona…', 'id' => null, 'disabled' => false])

{{--
    Reemplaza el <select> nativo: su lista desplegable abierta no se puede
    re-estilar con CSS (el resaltado azul del sistema operativo en la opción
    activa, por ejemplo, es fijo). Este es un desplegable propio con Alpine,
    con el mismo look que el resto de los inputs del sistema. El valor real
    sigue viajando por wire:model igual que con un select normal.

    Si no hay espacio abajo (por ejemplo, dentro de un modal con
    overflow-y-auto y este select cerca del borde inferior, como el
    selector de horas), la lista se abre hacia arriba en vez de hacia
    abajo. Ojo: se probó primero con x-teleport + position:fixed para
    directamente "escapar" del contenedor con scroll, pero esa técnica
    resultó frágil -- Livewire vuelve a renderizar este componente en
    cualquier re-render de la página (por ejemplo, por un wire:model.live
    en OTRO campo del mismo formulario) y su diff del DOM no sabe que
    Alpine ya movió el <ul> a <body>, dejando el HTML/JS del componente
    sin procesar y visible como texto en la página. wire:ignore no evitó
    el problema. Abrir hacia arriba es menos elegante pero no tiene ese
    riesgo: el nodo nunca sale de su lugar en el DOM.
--}}
@php
    $wireModelo = $attributes->wire('model');
    $propiedad = $wireModelo->value();
    $enVivo = $wireModelo->hasModifier('live') ? 'true' : 'false';
    $listaOpciones = collect($options)
        ->map(fn ($etiqueta, $valor) => ['valor' => (string) $valor, 'etiqueta' => $etiqueta])
        ->values()
        ->all();
@endphp

<div
    x-data="{
        abierto: false,
        resaltado: -1,
        opciones: @js($listaOpciones),
        abrirHaciaArriba: false,
        etiquetaDe(valor) {
            const opcion = this.opciones.find((o) => o.valor === String(valor ?? ''));
            return opcion ? opcion.etiqueta : @js($placeholder);
        },
        elegir(valor) {
            this.$wire.set('{{ $propiedad }}', valor, {{ $enVivo }});
            this.abierto = false;
            this.resaltado = -1;
        },
        abrir() {
            this.resaltado = this.opciones.findIndex((o) => o.valor === String(this.$wire.get('{{ $propiedad }}') ?? ''));

            const rect = this.$refs.boton.getBoundingClientRect();
            const espacioAbajo = window.innerHeight - rect.bottom;
            this.abrirHaciaArriba = espacioAbajo < 240 && rect.top > espacioAbajo;

            this.abierto = true;
        },
        moverResaltado(delta) {
            if (! this.abierto) { this.abrir(); return; }
            const total = this.opciones.length;
            if (total === 0) return;
            this.resaltado = ((this.resaltado + delta) % total + total) % total;
        },
        confirmarResaltado() {
            if (this.abierto && this.resaltado >= 0) this.elegir(this.opciones[this.resaltado].valor);
        },
    }"
    @click.outside="abierto = false"
    @keydown.escape="abierto = false"
    class="relative"
>
    <button
        type="button"
        x-ref="boton"
        @click="abierto ? (abierto = false) : abrir()"
        @keydown.arrow-down.prevent="moverResaltado(1)"
        @keydown.arrow-up.prevent="moverResaltado(-1)"
        @keydown.enter.prevent="confirmarResaltado()"
        @disabled($disabled)
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'flex w-full items-center justify-between gap-2 rounded-md border-border bg-surface px-3 py-2 text-left text-sm shadow-sm focus:border-accent focus:ring-accent disabled:opacity-60']) }}
    >
        <span x-text="etiquetaDe($wire.get('{{ $propiedad }}'))" :class="$wire.get('{{ $propiedad }}') ? 'text-ink' : 'text-ink-faint'"></span>
        <x-heroicon-o-chevron-up-down class="h-4 w-4 shrink-0 text-ink-faint" />
    </button>

    <ul
        x-show="abierto"
        x-cloak
        x-transition.origin.top
        role="listbox"
        :class="abrirHaciaArriba ? 'bottom-full mb-1' : 'top-full mt-1'"
        class="absolute z-30 max-h-60 w-full overflow-auto rounded-lg border border-border bg-surface py-1 shadow-lg"
    >
        <template x-for="(opcion, indice) in opciones" :key="opcion.valor">
            <li
                role="option"
                @click="elegir(opcion.valor)"
                @mouseenter="resaltado = indice"
                x-text="opcion.etiqueta"
                :class="opcion.valor === String($wire.get('{{ $propiedad }}') ?? '') ? 'bg-accent-soft text-accent' : (indice === resaltado ? 'bg-surface-2 text-ink' : 'text-ink')"
                class="cursor-pointer px-3 py-2 text-sm"
            ></li>
        </template>
    </ul>
</div>
