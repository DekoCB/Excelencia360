<?php

use App\Modules\Pagos\Enums\MedioCuentaEnum;
use App\Modules\Pagos\Enums\TipoBilleteraEnum;
use App\Modules\Pagos\Models\CuentaBancaria;
use App\Modules\Pagos\Services\CuentaBancariaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $medio = 'banco';

    public string $banco = '';

    public string $numeroCuenta = '';

    public string $cci = '';

    public string $tipoBilletera = '';

    public string $celular = '';

    public string $titular = '';

    public bool $activa = true;

    public $qr = null;

    public function mount(): void
    {
        Gate::authorize('tesoreria.gestionar');
    }

    public function abrirModal(?int $cuentaId = null): void
    {
        $this->resetValidation();
        $this->editandoId = $cuentaId;
        $this->qr = null;

        if ($cuentaId) {
            $cuenta = CuentaBancaria::query()->findOrFail($cuentaId);
            $this->medio = $cuenta->medio->value;
            $this->banco = (string) $cuenta->banco;
            $this->numeroCuenta = (string) $cuenta->numero_cuenta;
            $this->cci = (string) $cuenta->cci;
            $this->tipoBilletera = $cuenta->tipo_billetera?->value ?? '';
            $this->celular = (string) $cuenta->celular;
            $this->titular = $cuenta->titular;
            $this->activa = $cuenta->activa;
        } else {
            $this->reset(['banco', 'numeroCuenta', 'cci', 'tipoBilletera', 'celular', 'titular']);
            $this->medio = 'banco';
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(CuentaBancariaService $service): void
    {
        $reglas = [
            'medio' => ['required', Rule::in(array_column(MedioCuentaEnum::cases(), 'value'))],
            'titular' => 'required|string|max:100',
            'qr' => 'nullable|image|max:2048',
        ];

        if ($this->medio === MedioCuentaEnum::BANCO->value) {
            $reglas['banco'] = 'required|string|max:50';
            $reglas['numeroCuenta'] = 'required|string|max:30';
            $reglas['cci'] = 'nullable|string|max:30';
        } else {
            $reglas['tipoBilletera'] = ['required', Rule::in(array_column(TipoBilleteraEnum::cases(), 'value'))];
            $reglas['celular'] = 'required|string|max:20';
        }

        $this->validate($reglas);

        $medio = MedioCuentaEnum::from($this->medio);
        $esBanco = $medio === MedioCuentaEnum::BANCO;

        if ($this->editandoId) {
            $service->actualizar(
                CuentaBancaria::query()->findOrFail($this->editandoId),
                $medio,
                $esBanco ? $this->banco : null,
                $esBanco ? $this->numeroCuenta : null,
                $esBanco ? ($this->cci ?: null) : null,
                $esBanco ? null : TipoBilleteraEnum::from($this->tipoBilletera),
                $esBanco ? null : $this->celular,
                $this->titular,
                $this->activa,
                $this->qr,
            );
        } else {
            $service->crear(
                $medio,
                $esBanco ? $this->banco : null,
                $esBanco ? $this->numeroCuenta : null,
                $esBanco ? ($this->cci ?: null) : null,
                $esBanco ? null : TipoBilleteraEnum::from($this->tipoBilletera),
                $esBanco ? null : $this->celular,
                $this->titular,
                $this->qr,
            );
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Cuenta guardada correctamente.');
    }

    public function with(CuentaBancariaService $service): array
    {
        return [
            'cuentas' => $service->listar(),
            'medios' => MedioCuentaEnum::cases(),
            'tiposBilletera' => TipoBilleteraEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Cuentas para pagar</h1>
        <p class="mt-1 text-sm text-ink-dim">Cuentas bancarias y billeteras digitales que ven los estudiantes para pagar su mensualidad.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    <div class="mb-4 flex justify-end">
        <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
            <x-heroicon-o-plus class="h-4 w-4" />
            Nueva cuenta
        </x-primary-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @forelse ($cuentas as $cuenta)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">{{ $cuenta->medio->label() }}</p>
                        @if ($cuenta->medio === MedioCuentaEnum::BANCO)
                            <p class="font-display text-lg text-ink">{{ $cuenta->banco }}</p>
                            <p class="text-sm text-ink-dim">{{ $cuenta->numero_cuenta }}</p>
                            @if ($cuenta->cci)
                                <p class="text-xs text-ink-faint">CCI {{ $cuenta->cci }}</p>
                            @endif
                        @else
                            <p class="font-display text-lg text-ink">{{ $cuenta->tipo_billetera?->label() }}</p>
                            <p class="text-sm text-ink-dim">{{ $cuenta->celular }}</p>
                        @endif
                        <p class="mt-1 text-xs text-ink-faint">{{ $cuenta->titular }}</p>
                    </div>
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $cuenta->activa, 'bg-ink-faint/10 text-ink-faint' => ! $cuenta->activa])>
                        {{ $cuenta->activa ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    @if ($cuenta->getFirstMedia('qr'))
                        <a href="{{ $cuenta->getFirstMediaUrl('qr') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver QR</a>
                    @else
                        <span class="text-xs text-ink-faint">Sin QR</span>
                    @endif
                    <button wire:click="abrirModal({{ $cuenta->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                </div>
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No hay cuentas registradas.</p>
        @endforelse
    </div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        wire:click.self="$set('mostrarModal', false)"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="w-full max-w-md rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar cuenta' : 'Nueva cuenta' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="medio" value="Medio" />
                        <x-select-input
                            wire:model.live="medio"
                            id="medio"
                            class="mt-1 block w-full"
                            :options="collect($medios)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('medio')" class="mt-1" />
                    </div>

                    @if ($medio === 'banco')
                        <div wire:key="campos-banco" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="banco" value="Banco" />
                                    <x-text-input wire:model="banco" id="banco" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('banco')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="numeroCuenta" value="N.° de cuenta" />
                                    <x-text-input wire:model="numeroCuenta" id="numeroCuenta" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('numeroCuenta')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="cci" value="CCI (opcional)" />
                                <x-text-input wire:model="cci" id="cci" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('cci')" class="mt-1" />
                            </div>
                        </div>
                    @else
                        <div wire:key="campos-billetera" class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="tipoBilletera" value="Billetera" />
                                <x-select-input
                                    wire:model="tipoBilletera"
                                    id="tipoBilletera"
                                    class="mt-1 block w-full"
                                    placeholder="Selecciona…"
                                    :options="collect($tiposBilletera)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])"
                                />
                                <x-input-error :messages="$errors->get('tipoBilletera')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="celular" value="Celular" />
                                <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                            </div>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="titular" value="Titular" />
                        <x-text-input wire:model="titular" id="titular" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('titular')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="qr" value="Imagen QR (opcional)" />
                        <input wire:model="qr" id="qr" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('qr')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                            Cuenta activa
                        </label>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
