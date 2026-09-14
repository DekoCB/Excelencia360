<?php

use App\Modules\Docentes\Models\Contrato;
use App\Modules\Docentes\Services\ContratoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $termino = '';

    public bool $mostrarModal = false;

    public ?int $contratoId = null;

    public string $docenteId = '';

    public string $tipo = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $monto = '';

    public string $observaciones = '';

    public $documento = null;

    public function mount(): void
    {
        Gate::authorize('contratos.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('contratos.gestionar');

        $this->reset(['contratoId', 'docenteId', 'tipo', 'fechaInicio', 'fechaFin', 'monto', 'observaciones', 'documento']);
        $this->fechaInicio = now()->format('Y-m-d');
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $contratoId): void
    {
        Gate::authorize('contratos.gestionar');

        $contrato = Contrato::query()->findOrFail($contratoId);

        $this->contratoId = $contrato->id;
        $this->docenteId = (string) $contrato->docente_id;
        $this->tipo = $contrato->tipo;
        $this->fechaInicio = $contrato->fecha_inicio->format('Y-m-d');
        $this->fechaFin = $contrato->fecha_fin?->format('Y-m-d') ?? '';
        $this->monto = $contrato->monto !== null ? (string) $contrato->monto : '';
        $this->observaciones = $contrato->observaciones ?? '';
        $this->documento = null;
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function guardar(ContratoService $service): void
    {
        Gate::authorize('contratos.gestionar');

        $this->validate([
            'docenteId' => 'required|integer|exists:docentes,id',
            'tipo' => 'required|string|max:150',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'nullable|date|after:fechaInicio',
            'monto' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $datos = [
            'docenteId' => (int) $this->docenteId,
            'tipo' => $this->tipo,
            'fechaInicio' => $this->fechaInicio,
            'fechaFin' => $this->fechaFin !== '' ? $this->fechaFin : null,
            'monto' => $this->monto !== '' ? (float) $this->monto : null,
            'observaciones' => $this->observaciones !== '' ? $this->observaciones : null,
        ];

        if ($this->contratoId === null) {
            $service->registrar($datos, $this->documento, Auth::id());
            session()->flash('status', 'Contrato registrado correctamente.');
        } else {
            $service->actualizar(Contrato::query()->findOrFail($this->contratoId), $datos, $this->documento);
            session()->flash('status', 'Contrato actualizado correctamente.');
        }

        $this->mostrarModal = false;
    }

    public function eliminar(int $contratoId, ContratoService $service): void
    {
        Gate::authorize('contratos.gestionar');

        $service->eliminar(Contrato::query()->findOrFail($contratoId));

        session()->flash('status', 'Contrato eliminado.');
    }

    public function with(ContratoService $service): array
    {
        $contratos = $service->listar($this->termino ?: null);

        return [
            'contratos' => $contratos,
            'sugerencias' => $contratos->take(6)->map(fn (Contrato $contrato) => [
                'value' => $contrato->id,
                'label' => $contrato->docente->usuario->name,
            ])->values()->all(),
            'docentesDisponibles' => $service->docentesDisponibles(),
            'puedeGestionar' => Gate::allows('contratos.gestionar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Contratos</h1>
        <p class="mt-1 text-sm text-ink-dim">Contratos del personal docente, con su documento firmado.</p>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-buscador-combo
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por docente o DNI…"
            :sugerencias="$sugerencias"
        />

        @if ($puedeGestionar)
            <x-primary-button type="button" wire:click="abrirModalCrear" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo contrato
            </x-primary-button>
        @endif
    </div>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Docente</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Vigencia</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Documento</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($contratos as $contrato)
                    <tr wire:key="contrato-{{ $contrato->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $contrato->docente->usuario->name }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $contrato->tipo }}</td>
                        <td class="px-4 py-3 text-ink-dim">
                            {{ $contrato->fecha_inicio->format('d/m/Y') }} — {{ $contrato->fecha_fin?->format('d/m/Y') ?? 'indefinido' }}
                        </td>
                        <td class="px-4 py-3 text-ink-dim">{{ $contrato->monto !== null ? 'S/ '.number_format((float) $contrato->monto, 2) : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$contrato->estaVigente() ? 'ok' : 'neutral'">
                                {{ $contrato->estaVigente() ? 'Vigente' : 'Vencido' }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($contrato->getFirstMedia('documento'))
                                <a href="{{ $contrato->getFirstMediaUrl('documento') }}" target="_blank" class="text-sm font-medium text-accent hover:underline">Ver</a>
                            @else
                                <span class="text-ink-faint">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($puedeGestionar)
                                <button type="button" wire:click="abrirModalEditar({{ $contrato->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                                <button
                                    type="button"
                                    x-on:click="$store.confirm.preguntar('¿Eliminar este contrato?', () => $wire.eliminar({{ $contrato->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })"
                                    class="ml-3 text-sm font-medium text-danger hover:underline"
                                >Eliminar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-faint">No se encontraron contratos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $contratos->links() }}
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
            <h2 class="font-display text-lg text-ink">{{ $contratoId === null ? 'Nuevo contrato' : 'Editar contrato' }}</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="docenteId" value="Docente" />
                    <x-select-input
                        wire:model="docenteId"
                        id="docenteId"
                        placeholder="Selecciona un docente…"
                        class="mt-1 block w-full"
                        :options="$docentesDisponibles->mapWithKeys(fn ($d) => [$d->id => $d->usuario->name])"
                    />
                    <x-input-error :messages="$errors->get('docenteId')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="tipo" value="Tipo de contrato" />
                    <x-text-input wire:model="tipo" id="tipo" class="mt-1 block w-full" placeholder="Plazo fijo, locación de servicios…" />
                    <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="fechaInicio" value="Fecha de inicio" />
                        <x-date-input wire:model="fechaInicio" id="fechaInicio" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fechaFin" value="Fecha de fin (opcional)" />
                        <x-date-input wire:model="fechaFin" id="fechaFin" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="monto" value="Monto mensual (S/)" />
                    <x-text-input wire:model="monto" id="monto" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('monto')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="observaciones" value="Observaciones (opcional)" />
                    <textarea wire:model="observaciones" id="observaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('observaciones')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="documento" value="Documento firmado (PDF, opcional)" />
                    <input wire:model="documento" id="documento" type="file" accept="application/pdf" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('documento')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                    <x-primary-button type="submit">{{ $contratoId === null ? 'Registrar' : 'Guardar cambios' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
