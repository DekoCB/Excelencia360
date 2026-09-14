@props(['id' => null])

{{--
    Igual que <x-date-input>, pero además de la fecha permite elegir hora y
    minuto. Reemplaza al <input type="datetime-local"> nativo (que se ve
    fuera de lugar: fondo blanco, formato mm/dd/aaaa y AM/PM fijos por el
    navegador, sin poder forzar el locale desde el servidor). El valor real
    sigue viajando por wire:model en formato Y-m-d\TH:i, así que no cambia
    nada del lado del servidor.
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
        pad(n) { return String(n).padStart(2, '0') },
        partesDesdeIso(iso) {
            const m = String(iso ?? '').match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
            return m ? { anio: +m[1], mes: +m[2], dia: +m[3], hora: +m[4], minuto: +m[5] } : null;
        },
        hoyIso() {
            const d = new Date();
            return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}T${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`;
        },
        partesActuales() {
            return this.partesDesdeIso(this.$wire.get('{{ $propiedad }}')) ?? this.partesDesdeIso(this.hoyIso());
        },
        formatear(iso) {
            const p = this.partesDesdeIso(iso);
            return p ? `${this.pad(p.dia)}/${this.pad(p.mes)}/${p.anio} ${this.pad(p.hora)}:${this.pad(p.minuto)}` : '';
        },
        posicionarVista() {
            const base = this.partesActuales();
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
        aplicar(anio, mes, dia, hora, minuto) {
            const iso = `${anio}-${this.pad(mes)}-${this.pad(dia)}T${this.pad(hora)}:${this.pad(minuto)}`;
            this.$wire.set('{{ $propiedad }}', iso, {{ $enVivo }});
        },
        elegirDia(dia) {
            if (! dia) return;
            const p = this.partesActuales();
            this.aplicar(this.vistaAnio, this.vistaMes + 1, dia, p.hora, p.minuto);
        },
        cambiarHora(valor) {
            const p = this.partesActuales();
            const hora = Math.max(0, Math.min(23, parseInt(valor, 10) || 0));
            this.aplicar(p.anio, p.mes, p.dia, hora, p.minuto);
        },
        cambiarMinuto(valor) {
            const p = this.partesActuales();
            const minuto = Math.max(0, Math.min(59, parseInt(valor, 10) || 0));
            this.aplicar(p.anio, p.mes, p.dia, p.hora, minuto);
        },
        ahora() {
            const p = this.partesDesdeIso(this.hoyIso());
            this.aplicar(p.anio, p.mes, p.dia, p.hora, p.minuto);
            this.posicionarVista();
        },
        mesAnterior() {
            if (--this.vistaMes < 0) { this.vistaMes = 11; this.vistaAnio--; }
        },
        mesSiguiente() {
            if (++this.vistaMes > 11) { this.vistaMes = 0; this.vistaAnio++; }
        },
        limpiar() {
            this.$wire.set('{{ $propiedad }}', '', {{ $enVivo }});
            this.texto = '';
        },
        alEscribir(event) {
            const digitos = event.target.value.replace(/\D/g, '').slice(0, 12);
            let formateado = digitos;
            if (digitos.length > 10) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4, 8)} ${digitos.slice(8, 10)}:${digitos.slice(10)}`;
            else if (digitos.length > 8) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4, 8)} ${digitos.slice(8)}`;
            else if (digitos.length > 4) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4)}`;
            else if (digitos.length > 2) formateado = `${digitos.slice(0, 2)}/${digitos.slice(2)}`;
            this.texto = formateado;

            const m = formateado.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/);
            if (m) this.aplicar(+m[3], +m[2], +m[1], +m[4], +m[5]);
        },
        abrir() {
            this.posicionarVista();
            this.abierto = true;
        },
    }"
    x-effect="texto = formatear($wire.get('{{ $propiedad }}'))"
    @click.outside="abierto = false"
    @keydown.escape="abierto = false"
    class="relative"
>
    <div class="relative">
        <input
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="dd/mm/aaaa --:--"
            maxlength="16"
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

    <div
        x-show="abierto"
        x-cloak
        x-transition.origin.top
        @click.stop
        class="absolute z-30 mt-1 w-64 rounded-lg border border-border bg-surface p-3 shadow-lg"
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
                    @click="elegirDia(dia)"
                    x-text="dia ?? ''"
                    :class="dia === null ? 'invisible' :
                        esSeleccionado(dia) ? 'bg-accent text-white' :
                        esHoy(dia) ? 'font-semibold text-accent hover:bg-surface-2' :
                        'text-ink hover:bg-surface-2'"
                    class="mx-auto flex h-7 w-7 items-center justify-center rounded-md text-xs transition"
                ></button>
            </template>
        </div>

        <div class="mt-3 flex items-center justify-center gap-2 border-t border-border pt-3">
            <x-heroicon-o-clock class="h-4 w-4 text-ink-faint" />
            <input
                type="number"
                min="0"
                max="23"
                :value="pad(partesActuales().hora)"
                @input="cambiarHora($event.target.value)"
                @click.stop
                class="w-12 rounded-md border-border bg-surface-2 py-1 text-center text-sm text-ink focus:border-accent focus:ring-accent"
            >
            <span class="text-ink-faint">:</span>
            <input
                type="number"
                min="0"
                max="59"
                :value="pad(partesActuales().minuto)"
                @input="cambiarMinuto($event.target.value)"
                @click.stop
                class="w-12 rounded-md border-border bg-surface-2 py-1 text-center text-sm text-ink focus:border-accent focus:ring-accent"
            >
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-border pt-2 text-xs font-medium">
            <button type="button" @click="limpiar()" class="text-ink-faint transition hover:text-ink">Limpiar</button>
            <button type="button" @click="ahora()" class="text-accent transition hover:underline">Ahora</button>
        </div>
    </div>
</div>
