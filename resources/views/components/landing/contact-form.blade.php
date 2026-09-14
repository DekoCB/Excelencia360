@props(['asuntos', 'enviado' => false, 'errorEnvio' => false, 'errors'])

{{--
    Formulario de contacto. Se renderiza dentro del componente Volt
    landing/index, que aporta las propiedades enlazadas (wire:model), la
    acción enviarMensaje y el $errors de la validación. Estados: cargando
    (botón bloqueado + spinner), éxito (reemplaza al formulario) y error de
    envío (aviso encima del formulario, con los datos intactos).
--}}
@php
    $claseCampo = 'mt-1.5 block w-full rounded-lg border-e360-border bg-white text-e360-heading shadow-sm placeholder:text-slate-400 focus:border-e360-primary-deep focus:ring-e360-primary-deep';
    $claseCampoError = 'border-red-400 focus:border-red-500 focus:ring-red-500';
    $claseEtiqueta = 'block text-sm font-semibold text-e360-heading';
@endphp

<div {{ $attributes->class(['rounded-2xl border border-e360-border bg-white p-6 shadow-sm shadow-e360-heading/5 sm:p-8']) }}>
    @if ($enviado)
        <div class="flex flex-col items-center py-8 text-center" role="status" aria-live="polite">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-e360-primary-tint text-e360-primary-deep">
                <x-heroicon-o-check-circle class="h-8 w-8" aria-hidden="true" />
            </span>
            <h3 class="mt-5 font-brand text-xl font-bold text-e360-heading">Mensaje enviado</h3>
            <p class="mt-2 max-w-sm text-sm leading-relaxed text-e360-text">
                Gracias por escribirnos. Revisaremos tu consulta y te responderemos al correo o teléfono que indicaste.
            </p>
            <x-landing.cta-button type="button" variant="secondary" size="sm" class="mt-6" wire:click="$set('enviado', false)">
                Enviar otro mensaje
            </x-landing.cta-button>
        </div>
    @else
        <h3 class="font-brand text-xl font-bold text-e360-heading">Envíanos un mensaje</h3>
        <p class="mt-1 text-sm text-e360-muted">Cuéntanos qué curso o servicio te interesa y te responderemos a la brevedad.</p>

        @if ($errorEnvio)
            <div class="mt-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" aria-hidden="true" />
                <div class="text-sm">
                    <p class="font-semibold text-red-800">No pudimos enviar tu mensaje</p>
                    <p class="mt-0.5 text-red-700">Ocurrió un error al guardarlo. Tus datos siguen en el formulario: inténtalo de nuevo en unos segundos.</p>
                </div>
            </div>
        @endif

        <form wire:submit="enviarMensaje" class="mt-6 space-y-5" novalidate>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="contacto-nombre" class="{{ $claseEtiqueta }}">Nombre</label>
                    <input
                        type="text"
                        id="contacto-nombre"
                        wire:model="nombre"
                        autocomplete="name"
                        aria-required="true"
                        @if ($errors->has('nombre')) aria-invalid="true" aria-describedby="contacto-nombre-error" @endif
                        class="{{ $claseCampo }} {{ $errors->has('nombre') ? $claseCampoError : '' }}"
                        placeholder="Tu nombre y apellido"
                    >
                    @error('nombre') <p id="contacto-nombre-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contacto-email" class="{{ $claseEtiqueta }}">Correo electrónico</label>
                    <input
                        type="email"
                        id="contacto-email"
                        wire:model="email"
                        autocomplete="email"
                        inputmode="email"
                        aria-required="true"
                        @if ($errors->has('email')) aria-invalid="true" aria-describedby="contacto-email-error" @endif
                        class="{{ $claseCampo }} {{ $errors->has('email') ? $claseCampoError : '' }}"
                        placeholder="tu@correo.com"
                    >
                    @error('email') <p id="contacto-email-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="contacto-telefono" class="{{ $claseEtiqueta }}">Teléfono</label>
                    <input
                        type="tel"
                        id="contacto-telefono"
                        wire:model="telefono"
                        autocomplete="tel"
                        inputmode="tel"
                        aria-required="true"
                        @if ($errors->has('telefono')) aria-invalid="true" aria-describedby="contacto-telefono-error" @endif
                        class="{{ $claseCampo }} {{ $errors->has('telefono') ? $claseCampoError : '' }}"
                        placeholder="9XX XXX XXX"
                    >
                    @error('telefono') <p id="contacto-telefono-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contacto-asunto" class="{{ $claseEtiqueta }}">Asunto</label>
                    <select
                        id="contacto-asunto"
                        wire:model="asunto"
                        aria-required="true"
                        @if ($errors->has('asunto')) aria-invalid="true" aria-describedby="contacto-asunto-error" @endif
                        class="{{ $claseCampo }} {{ $errors->has('asunto') ? $claseCampoError : '' }}"
                    >
                        <option value="">Selecciona un asunto</option>
                        @foreach ($asuntos as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('asunto') <p id="contacto-asunto-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="contacto-mensaje" class="{{ $claseEtiqueta }}">Mensaje</label>
                <textarea
                    id="contacto-mensaje"
                    wire:model="mensaje"
                    rows="5"
                    aria-required="true"
                    @if ($errors->has('mensaje')) aria-invalid="true" aria-describedby="contacto-mensaje-error" @endif
                    class="{{ $claseCampo }} {{ $errors->has('mensaje') ? $claseCampoError : '' }}"
                    placeholder="Cuéntanos en qué podemos ayudarte…"
                ></textarea>
                @error('mensaje') <p id="contacto-mensaje-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-e360-muted">Todos los campos son obligatorios.</p>
                <x-landing.cta-button
                    type="submit"
                    variant="accent"
                    class="w-full sm:w-auto"
                    wire:loading.attr="disabled"
                    wire:target="enviarMensaje"
                >
                    <svg wire:loading wire:target="enviarMensaje" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="enviarMensaje">Enviar mensaje</span>
                    <span wire:loading wire:target="enviarMensaje">Enviando…</span>
                </x-landing.cta-button>
            </div>
        </form>
    @endif
</div>
