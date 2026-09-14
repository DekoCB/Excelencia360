@props(['sugerencias' => [], 'placeholder' => 'Buscar…'])

@php
    $propiedad = $attributes->wire('model')->value();
@endphp

{{--
    Envuelve el buscador de texto que ya usan las páginas de listado (el
    wire:model.live.debounce sigue funcionando igual, sin tocar) y le
    agrega, con Alpine, una lista desplegable de sugerencias en vivo
    debajo -- así es "escribir" (filtra la tabla, como siempre) y "lista
    desplegable" (clic en una sugerencia salta directo a ese resultado) a
    la vez. Las sugerencias ya vienen calculadas del servidor (los
    primeros resultados de la misma búsqueda), no dispara ninguna
    consulta nueva.
--}}
<div
    x-data="{
        abierto: false,
        sugerencias: @js($sugerencias),
        elegir(etiqueta) {
            this.$wire.set('{{ $propiedad }}', etiqueta, true);
            this.abierto = false;
        },
    }"
    @click.outside="abierto = false"
    @keydown.escape="abierto = false"
    class="relative w-full sm:max-w-xs"
>
    <input
        type="search"
        @focus="abierto = true"
        @input="abierto = true"
        {{ $attributes->merge(['class' => 'w-full rounded-md border-border bg-surface text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent']) }}
        placeholder="{{ $placeholder }}"
    >

    <ul
        x-show="abierto && sugerencias.length > 0"
        x-cloak
        x-transition.origin.top
        role="listbox"
        class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-border bg-surface py-1 shadow-lg"
    >
        <template x-for="sugerencia in sugerencias" :key="sugerencia.value">
            <li
                role="option"
                @click="elegir(sugerencia.label)"
                x-text="sugerencia.label"
                class="cursor-pointer px-3 py-2 text-sm text-ink hover:bg-surface-2"
            ></li>
        </template>
    </ul>
</div>
