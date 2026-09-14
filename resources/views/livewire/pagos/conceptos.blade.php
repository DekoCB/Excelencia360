<?php

use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\SolicitudCambioMonto;
use App\Modules\Pagos\Services\ConceptoPagoService;
use App\Modules\Pagos\Services\SolicitudCambioMontoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $tipo = '';

    public string $montoBase = '';

    public bool $activo = true;

    /** @var array<int, string> */
    public array $motivoRechazoMonto = [];

    public function mount(): void
    {
        Gate::authorize('pagos.gestionar');
    }

    public function abrirModal(?int $conceptoId = null): void
    {
        $this->resetValidation();
        $this->editandoId = $conceptoId;

        if ($conceptoId) {
            $concepto = ConceptoPago::query()->findOrFail($conceptoId);
            $this->nombre = $concepto->nombre;
            $this->tipo = $concepto->tipo->value;
            $this->montoBase = (string) $concepto->monto_base;
            $this->activo = $concepto->activo;
        } else {
            $this->reset(['nombre', 'tipo', 'montoBase']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(ConceptoPagoService $service): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoConceptoEnum::cases(), 'value')),
            'montoBase' => 'required|numeric|min:0',
        ]);

        $tipo = TipoConceptoEnum::from($this->tipo);

        if ($this->editandoId) {
            $concepto = ConceptoPago::query()->findOrFail($this->editandoId);
            $cambioDeMonto = (float) $concepto->monto_base !== (float) $this->montoBase;

            $service->actualizar($concepto, $this->nombre, $tipo, (float) $this->montoBase, $this->activo, Auth::id());

            session()->flash('status', $cambioDeMonto
                ? 'Concepto actualizado. El cambio de monto queda pendiente de aprobación de dirección.'
                : 'Concepto de pago guardado correctamente.');
        } else {
            $service->crear($this->nombre, $tipo, (float) $this->montoBase);
            session()->flash('status', 'Concepto de pago guardado correctamente.');
        }

        $this->mostrarModal = false;
    }

    public function aprobarCambioMonto(int $solicitudId, SolicitudCambioMontoService $service): void
    {
        Gate::authorize('pagos.aprobar_montos');

        $service->aprobar(SolicitudCambioMonto::query()->findOrFail($solicitudId), Auth::id());

        session()->flash('status', 'Cambio de monto aprobado y aplicado.');
    }

    public function rechazarCambioMonto(int $solicitudId, SolicitudCambioMontoService $service): void
    {
        Gate::authorize('pagos.aprobar_montos');

        $this->validate(['motivoRechazoMonto.'.$solicitudId => 'required|string|max:255']);

        $service->rechazar(SolicitudCambioMonto::query()->findOrFail($solicitudId), Auth::id(), $this->motivoRechazoMonto[$solicitudId]);

        unset($this->motivoRechazoMonto[$solicitudId]);
        session()->flash('status', 'Cambio de monto rechazado.');
    }

    public function with(ConceptoPagoService $service, SolicitudCambioMontoService $solicitudes): array
    {
        $puedeAprobarMontos = Auth::user()->hasPermissionTo('pagos.aprobar_montos');

        return [
            'conceptos' => $service->listar()->load(['solicitudesCambioMonto' => fn ($query) => $query->where('estado', 'pendiente')]),
            'tipos' => TipoConceptoEnum::cases(),
            'puedeAprobarMontos' => $puedeAprobarMontos,
            'solicitudesPendientes' => $puedeAprobarMontos ? $solicitudes->pendientes() : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Conceptos de pago</h1>
        <p class="mt-1 text-sm text-ink-dim">Catálogo de conceptos facturables: matrícula, mensualidad, certificados y más.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    <div class="mb-4 flex justify-end">
        <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
            <x-heroicon-o-plus class="h-4 w-4" />
            Nuevo concepto
        </x-primary-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($puedeAprobarMontos && $solicitudesPendientes->isNotEmpty())
        <div class="mb-6 divide-y divide-border rounded-lg border border-warn/30 bg-surface">
            <div class="px-4 py-3">
                <h2 class="font-display text-sm text-ink">Solicitudes de cambio de monto pendientes</h2>
                <p class="text-xs text-ink-faint">Ningún cambio de precio se aplica hasta que lo apruebes acá.</p>
            </div>
            @foreach ($solicitudesPendientes as $solicitud)
                <div class="px-4 py-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $solicitud->concepto->nombre }}</p>
                            <p class="text-xs text-ink-faint">
                                S/ {{ number_format((float) $solicitud->monto_actual, 2) }} → S/ {{ number_format((float) $solicitud->monto_propuesto, 2) }}
                                · pedido por {{ $solicitud->solicitadoPor?->name ?? '—' }} el {{ $solicitud->fecha_solicitud->format('d/m/Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-secondary-button type="button" x-on:click="$store.confirm.preguntar('¿Aprobar este cambio de monto? Se aplicará de inmediato.', () => $wire.aprobarCambioMonto({{ $solicitud->id }}), { etiquetaConfirmar: 'Aprobar' })">
                            Aprobar
                        </x-secondary-button>
                        <input
                            type="text"
                            wire:model="motivoRechazoMonto.{{ $solicitud->id }}"
                            placeholder="Motivo de rechazo…"
                            class="w-52 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                        >
                        <button type="button" wire:click="rechazarCambioMonto({{ $solicitud->id }})" class="text-xs font-medium text-danger hover:underline">Rechazar</button>
                        <x-input-error :messages="$errors->get('motivoRechazoMonto.'.$solicitud->id)" class="mt-0" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto base</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($conceptos as $concepto)
                    <tr wire:key="concepto-{{ $concepto->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $concepto->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $concepto->tipo->label() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">
                            S/ {{ number_format((float) $concepto->monto_base, 2) }}
                            @if ($concepto->solicitudesCambioMonto->isNotEmpty())
                                <span class="ml-1 rounded-full bg-warn/15 px-2 py-0.5 text-xs font-sans text-warn">
                                    Pendiente: S/ {{ number_format((float) $concepto->solicitudesCambioMonto->first()->monto_propuesto, 2) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $concepto->activo, 'bg-ink-faint/10 text-ink-faint' => ! $concepto->activo])>
                                {{ $concepto->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="abrirModal({{ $concepto->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay conceptos de pago registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
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
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar concepto' : 'Nuevo concepto' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="tipo" value="Tipo" />
                            <x-select-input
                                wire:model="tipo"
                                id="tipo"
                                class="mt-1 block w-full"
                                :options="collect($tipos)->mapWithKeys(fn ($tipoOpcion) => [$tipoOpcion->value => $tipoOpcion->label()])"
                            />
                            <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="montoBase" value="Monto base (S/)" />
                            <x-text-input wire:model="montoBase" id="montoBase" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('montoBase')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Concepto activo
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
