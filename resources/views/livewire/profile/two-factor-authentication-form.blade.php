<?php

use App\Modules\Identidad\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $habilitando = false;

    public string $codigoConfirmacion = '';

    public string $passwordActual = '';

    /** @var list<string> */
    public array $codigosRecuperacion = [];

    public bool $mostrandoCodigos = false;

    public function habilitar(TwoFactorAuthenticationService $service): void
    {
        $service->generarSecreto(Auth::user());
        $this->habilitando = true;
    }

    public function confirmar(TwoFactorAuthenticationService $service): void
    {
        $this->validate(['codigoConfirmacion' => 'required|string|max:30']);

        $codigos = $service->confirmar(Auth::user(), $this->codigoConfirmacion);

        if (! $codigos) {
            throw ValidationException::withMessages(['codigoConfirmacion' => 'El código no es válido.']);
        }

        $this->habilitando = false;
        $this->codigoConfirmacion = '';
        $this->codigosRecuperacion = $codigos;
        $this->mostrandoCodigos = true;

        $this->dispatch('two-factor-enabled');
    }

    public function cancelar(TwoFactorAuthenticationService $service): void
    {
        $service->deshabilitar(Auth::user());
        $this->habilitando = false;
        $this->codigoConfirmacion = '';
    }

    public function regenerarCodigos(TwoFactorAuthenticationService $service): void
    {
        $this->codigosRecuperacion = $service->regenerarCodigosRecuperacion(Auth::user());
        $this->mostrandoCodigos = true;
    }

    public function deshabilitar(TwoFactorAuthenticationService $service): void
    {
        $this->validate(['passwordActual' => 'required|current_password']);

        $service->deshabilitar(Auth::user());
        $this->passwordActual = '';
        $this->mostrandoCodigos = false;

        $this->dispatch('two-factor-disabled');
    }

    public function with(TwoFactorAuthenticationService $service): array
    {
        $user = Auth::user();

        return [
            'activo' => $user->two_factor_confirmed_at !== null,
            'qrCodeUrl' => $this->habilitando ? $service->qrCodeDataUrl($user) : null,
            'secreto' => $this->habilitando ? $user->two_factor_secret : null,
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">Autenticación de dos factores</h2>
        <p class="mt-1 text-sm text-ink-dim">
            Agrega una capa extra de seguridad pidiendo un código de tu app autenticadora (Google Authenticator, Authy, 1Password…) al iniciar sesión.
        </p>
    </header>

    <div class="mt-6 space-y-6">
        @if ($activo && ! $habilitando)
            <div class="flex items-center gap-2 text-sm text-ok">
                <x-heroicon-o-shield-check class="h-5 w-5" />
                La autenticación de dos factores está activa.
            </div>

            @if ($mostrandoCodigos)
                <div class="rounded-md border border-warn/30 bg-warn/10 p-4">
                    <p class="text-sm font-medium text-warn">
                        Guarda estos códigos de recuperación en un lugar seguro. Cada uno sirve una sola vez y no volverán a mostrarse.
                    </p>
                    <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-ink">
                        @foreach ($codigosRecuperacion as $codigo)
                            <span>{{ $codigo }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-secondary-button type="button" x-on:click="$store.confirm.preguntar('Esto invalida tus códigos de recuperación actuales. ¿Continuar?', () => $wire.regenerarCodigos(), { peligro: true, etiquetaConfirmar: 'Regenerar' })">
                Regenerar códigos de recuperación
            </x-secondary-button>

            <div class="max-w-sm border-t border-border pt-6">
                <x-input-label for="two_factor_password" value="Contraseña actual" />
                <x-text-input wire:model="passwordActual" id="two_factor_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('passwordActual')" class="mt-2" />
                <x-danger-button type="button" x-on:click="$store.confirm.preguntar('¿Desactivar la autenticación de dos factores?', () => $wire.deshabilitar(), { peligro: true, etiquetaConfirmar: 'Desactivar' })" class="mt-3">
                    Desactivar
                </x-danger-button>
            </div>
        @elseif ($habilitando)
            <div class="max-w-sm space-y-4">
                <img src="{{ $qrCodeUrl }}" alt="Código QR de autenticación de dos factores" class="rounded-md border border-border bg-white p-2">

                <p class="text-xs text-ink-faint">
                    ¿No puedes escanear el código? Ingresa esta clave manualmente en tu app:
                    <span class="block font-mono text-ink-dim">{{ $secreto }}</span>
                </p>

                <div>
                    <x-input-label for="codigoConfirmacion" value="Código de 6 dígitos" />
                    <x-text-input wire:model="codigoConfirmacion" id="codigoConfirmacion" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full" autofocus />
                    <x-input-error :messages="$errors->get('codigoConfirmacion')" class="mt-2" />
                </div>

                <div class="flex gap-3">
                    <x-primary-button type="button" wire:click="confirmar">Confirmar y activar</x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelar">Cancelar</x-secondary-button>
                </div>
            </div>
        @else
            <x-primary-button type="button" wire:click="habilitar">Habilitar autenticación de dos factores</x-primary-button>
        @endif
    </div>
</section>
