@props(['id' => null])

{{--
    Un <input type="date"> nativo muestra mm/dd/aaaa o dd/mm/aaaa según el
    idioma configurado en el navegador/SO del usuario, no según nuestro
    locale — no hay forma de forzar el formato desde el servidor (probado).
    Este componente es un selector de calendario propio: un campo de texto
    con máscara dd/mm/aaaa más un desplegable de calendario en español para
    elegir el día con un clic. El valor real sigue viajando por wire:model
    en formato Y-m-d, así que no cambia nada del lado del servidor.
--}}
@php
    $wireModelo = $attributes->wire('model');
    $propiedad = $wireModelo->value();
    $enVivo = $wireModelo->hasModifier('live') ? 'true' : 'false';
@endphp

<div
    x-data="{
        abierto: false,
        texto: '',
        vistaAnio: null,
        vistaMes: null,
        meses: ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        diasSemana: ['L', 'M', 'X', 'J', 'V', 'S', 'D'],
        partesDesdeIso(iso) {
            const m = String(iso ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? { anio: +m[1], mes: +m[2], dia: +m[3] } : null;
        },
        hoyIso() {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },
        formatear(iso) {
            const p = this.partesDesdeIso(iso);
            return p ? `${String(p.dia).padStart(2, '0')}/${String(p.mes).padStart(2, '0')}/${p.anio}` : '';
        },
        posicionarVista() {
            const base = this.partesDesdeIso(this.$wire.get('{{ $propiedad }}')) ?? this.partesDesdeIso(this.hoyIso());
            this.vistaAnio = base.anio;
            this.vistaMes = base.mes - 1;
        },
        diasDelMes() {
            const total = new Date(this.vistaAnio, this.vistaMes + 1, 0).getDate();
            const primerDia = (new Date(this.vistaAnio, this.vistaMes, 1).getDay() + 6) % 7;
            return Array(primerDia).fill(null).concat(Array.from({ length: total }, (_, i) => i + 1));
        },
        esSeleccionado(dia) {
            const p = this.partesDesdeIso(this.$wire.get('{{ $propiedad }}'));
            return !!p && dia === p.dia && this.vistaMes === p.mes - 1 && this.vistaAnio === p.anio;
        },
        esHoy(dia) {
            const h = this.partesDesdeIso(this.hoyIso());
            return dia === h.dia && this.vistaMes === h.mes - 1 && this.vistaAnio === h.anio;
        },
        elegir(dia) {
            if (! dia) return;
            const iso = `${this.vistaAnio}-${String(this.vistaMes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
            this.$wire.set('{{ $propiedad }}', iso, {{ $enVivo }});
            this.abierto = false;
        },
        mesAnterior() {
            if (--this.vistaMes < 0) { this.vistaMes = 11; this.vistaAnio--; }
        },
        mesSiguiente() {
            if (++this.vistaMes > 11) { this.vistaMes = 0; this.vistaAnio++; }
        },
        alEscribir(event) {
            const digitos = event.target.value.replace(/\D/g, '').slice(0, 8);
            let formateado = digitos;
            if (digitos.length > 4) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4)}`;
            else if (digitos.length > 2) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2)}`;
            this.texto = formateado;

            const m = formateado.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
            if (m) this.$wire.set('{{ $propiedad }}', `${m[3]}-${m[2]}-${m[1]}`, {{ $enVivo }});
        },
        posicion: { top: 0, left: 0 },
        // El calendario se teletransporta a <body> (ver x-teleport abajo) para
        // no quedar recortado por contenedores con overflow propio (p. ej. el
        // cuerpo con scroll de un modal) -- por eso se posiciona con
        // coordenadas fijas calculadas del campo, no con 'absolute' relativo
        // a este wrapper.
        posicionarCalendario() {
            const rect = this.$refs.campo.getBoundingClientRect();
            const alturaCalendario = 300;
            const abajoCabe = rect.bottom + 4 + alturaCalendario <= window.innerHeight;

            this.posicion = {
                top: abajoCabe ? rect.bottom + 4 : Math.max(4, rect.top - alturaCalendario - 4),
                left: Math.min(rect.left, window.innerWidth - 260),
            };
        },
        abrir() {
            this.posicionarVista();
            this.posicionarCalendario();
            this.abierto = true;
        },
        cerrarSiEsAfuera(event) {
            if (! this.abierto) return;

            const dentroDeControles = this.$refs.controles.contains(event.target);
            const dentroDelCalendario = this.$refs.calendario && this.$refs.calendario.contains(event.target);

            if (! dentroDeControles && ! dentroDelCalendario) this.abierto = false;
        },
    }"
    x-effect="texto = formatear($wire.get('{{ $propiedad }}'))"
    @click.window="cerrarSiEsAfuera($event)"
    @keydown.escape="abierto = false"
    @scroll.window.capture="abierto && posicionarCalendario()"
    @resize.window="abierto && posicionarCalendario()"
    class="relative"
>
    <div class="relative" x-ref="controles">
        <input
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="dd/mm/aaaa"
            maxlength="10"
            x-ref="campo"
            :value="texto"
            @input="alEscribir"
            @focus="abrir()"
            @if ($id) id="{{ $id }}" @endif
            {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'rounded-md border-border bg-surface text-ink shadow-sm focus:border-accent focus:ring-accent disabled:opacity-60 pr-9']) }}
        >
        <button
            type="button"
            @click="abierto ? (abierto = false) : abrir()"
            class="absolute inset-y-0 right-0 flex items-center px-2.5 text-ink-faint transition hover:text-accent"
            tabindex="-1"
        >
            <x-heroicon-o-calendar-days class="h-4 w-4" />
        </button>
    </div>

    {{--
        Teletransportado a <body> para no quedar recortado por contenedores
        con overflow propio (p. ej. el cuerpo con scroll de un x-modal) --
        ver posicionarCalendario(), que calcula coordenadas fijas a partir
        del campo en vez de depender de position:absolute relativo a este
        wrapper.
    --}}
    <template x-teleport="body">
        <div
            x-ref="calendario"
            x-show="abierto"
            x-cloak
            x-transition.origin.top
            :style="`top: ${posicion.top}px; left: ${posicion.left}px;`"
            class="fixed z-[60] w-64 rounded-lg border border-border bg-surface p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center justify-between">
                <button type="button" @click="mesAnterior()" class="rounded p-1 text-ink-faint transition hover:bg-surface-2 hover:text-ink">
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </button>
                <p class="text-sm font-medium capitalize text-ink" x-text="`${meses[vistaMes]} ${vistaAnio}`"></p>
                <button type="button" @click="mesSiguiente()" class="rounded p-1 text-ink-faint transition hover:bg-surface-2 hover:text-ink">
                    <x-heroicon-o-chevron-right class="h-4 w-4" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-y-1 text-center">
                <template x-for="d in diasSemana" :key="d">
                    <span class="text-[11px] font-medium uppercase text-ink-faint" x-text="d"></span>
                </template>

                <template x-for="(dia, indice) in diasDelMes()" :key="indice">
                    <button
                        type="button"
                        :disabled="dia === null"
                        @click="elegir(dia)"
                        x-text="dia ?? ''"
                        :class="dia === null ? 'invisible' :
                            esSeleccionado(dia) ? 'bg-accent text-white' :
                            esHoy(dia) ? 'font-semibold text-accent hover:bg-surface-2' :
                            'text-ink hover:bg-surface-2'"
                        class="mx-auto flex h-7 w-7 items-center justify-center rounded-md text-xs transition"
                    ></button>
                </template>
            </div>
        </div>
    </template>
</div>
