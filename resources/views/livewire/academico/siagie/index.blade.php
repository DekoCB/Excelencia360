<?php

use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Services\SiagieService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public string $tipo = 'primero';

    public string $anio = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public function mount(): void
    {
        Gate::authorize('academico.ver');
        $this->anio = (string) now()->year;
    }

    public function abrirModal(): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->reset(['tipo', 'fechaInicio', 'fechaFin']);
        $this->anio = (string) now()->year;
        $this->mostrarModal = true;
    }

    public function guardar(SiagieService $service): void
    {
        Gate::authorize('academico.gestionar');

        $esAnual = $this->tipo === TipoSiagieEnum::ANUAL->value;

        $this->validate([
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoSiagieEnum::cases(), 'value')),
            'anio' => 'required|integer|min:2020|max:2100',
            'fechaInicio' => $esAnual ? 'required|date' : 'nullable|date',
            'fechaFin' => $esAnual ? 'required|date' : 'nullable|date',
        ]);

        $service->crear([
            'tipo' => TipoSiagieEnum::from($this->tipo),
            'anio' => (int) $this->anio,
            'fecha_inicio' => $this->fechaInicio ?: null,
            'fecha_fin' => $this->fechaFin ?: null,
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Periodo académico creado correctamente.');
    }

    public function with(SiagieService $service): array
    {
        return [
            'siagies' => $service->listar(),
            'tipos' => TipoSiagieEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Periodo Académico</h1>
        <p class="mt-1 text-sm text-ink-dim">Los periodos académicos (1.er periodo, 2.° periodo, Anual) — independientes del Período de Matrícula rotativo de la institución.</p>
    </x-slot>

    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo periodo académico
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Periodo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Fechas</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($siagies as $siagie)
                    <tr wire:key="siagie-{{ $siagie->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $siagie->nombreCompleto() }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $siagie->tipo->label() }}</td>
                        <td class="px-4 py-3 text-ink-dim">
                            {{ $siagie->fecha_inicio ? $siagie->fecha_inicio->format('d/m/Y').' – '.$siagie->fecha_fin->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$siagie->estado->value === 'activo' ? 'ok' : 'neutral'">
                                {{ $siagie->estado->label() }}
                            </x-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-ink-faint">No hay periodos académicos registrados.</td></tr>
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
            <h2 class="font-display text-lg text-ink">Nuevo periodo académico</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <x-select-input
                            wire:model.live="tipo"
                            id="tipo"
                            class="mt-1 block w-full"
                            :options="collect($tipos)->mapWithKeys(fn ($tipoOpcion) => [$tipoOpcion->value => $tipoOpcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="anio" value="Año" />
                        <x-text-input wire:model="anio" id="anio" type="number" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('anio')" class="mt-1" />
                    </div>
                </div>

                @if ($tipo === 'anual')
                    <p class="text-xs text-ink-dim">El periodo académico Anual crea además su propio Período de Matrícula, con horarios reales: declara de qué mes a qué mes dura el periodo de clases (8 meses; los 2 restantes son vacaciones).</p>
                @else
                    <p class="text-xs text-ink-dim">Las fechas son opcionales para este tipo — es solo una clasificación, sin horarios propios.</p>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="fechaInicio" value="Fecha inicio" />
                        <x-date-input wire:model="fechaInicio" id="fechaInicio" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fechaFin" value="Fecha fin" />
                        <x-date-input wire:model="fechaFin" id="fechaFin" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                    <x-primary-button type="submit">Crear periodo académico</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
