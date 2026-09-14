@props(['metodoField' => 'metodoEntrega', 'correoField' => 'correoEntrega', 'metodoActual' => ''])

@php
    $metodoEnum = \App\Shared\Enums\MetodoEntregaEnum::tryFrom($metodoActual);
@endphp

{{--
    Elección de entrega compartida por certificados, constancias y libreta:
    el estudiante elige recojo físico, envío digital o ambos, y solo si
    incluye digital se pide su correo personal. La usa mis-certificados.blade.php.
--}}
<div class="space-y-3">
    <div>
        <x-input-label value="Cómo deseas recibirlo" />
        <div class="mt-2 space-y-2">
            @foreach (\App\Shared\Enums\MetodoEntregaEnum::cases() as $opcion)
                <label class="flex items-center gap-2 rounded-md border border-border p-3 text-sm text-ink">
                    <input
                        type="radio"
                        value="{{ $opcion->value }}"
                        wire:model.live="{{ $metodoField }}"
                        class="border-border text-accent focus:ring-accent"
                    >
                    {{ $opcion->label() }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get($metodoField)" class="mt-1" />
    </div>

    @if ($metodoEnum?->requiereCorreo())
        <div>
            <x-input-label for="{{ $correoField }}" value="Correo electrónico personal" />
            <x-text-input wire:model="{{ $correoField }}" id="{{ $correoField }}" type="email" class="mt-1 block w-full" placeholder="tucorreo@ejemplo.com" />
            <x-input-error :messages="$errors->get($correoField)" class="mt-1" />
        </div>
    @endif

    @if ($metodoEnum?->requiereRecojo())
        <p class="rounded-md bg-accent-soft px-3 py-2 text-xs text-accent">Deberás acercarte a la institución para recogerlo una vez que esté listo.</p>
    @endif
</div>
