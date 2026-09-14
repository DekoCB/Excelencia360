<?php

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public string $nombre = '';

    public string $tipo = '';

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
        $this->reset(['nombre', 'tipo', 'fechaInicio', 'fechaFin']);
        $this->anio = (string) now()->year;
        $this->mostrarModal = true;
    }

    public function guardar(CicloService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoCicloEnum::cases(), 'value')),
            'anio' => 'required|integer|min:2020|max:2100',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ]);

        $service->crear([
            'nombre' => $this->nombre,
            'modalidad' => ModalidadCicloEnum::SEIS_MESES,
            'tipo' => TipoCicloEnum::from($this->tipo),
            'anio' => (int) $this->anio,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Grupo creado correctamente.');
    }

    public function with(CicloService $service): array
    {
        return [
            'ciclos' => $service->listar(),
            'tipos' => TipoCicloEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Grupos</h1>
        <p class="mt-1 text-sm text-ink-dim">4 ventanas de admisión rotativas al año: Grupo 1 ene-jun, Grupo 2 may-oct, Grupo 3 jul-dic, Grupo 4 nov-abr.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo grupo
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
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Fechas</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($ciclos as $ciclo)
                    <tr wire:key="ciclo-{{ $ciclo->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $ciclo->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $ciclo->tipo?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $ciclo->estado->value === 'activo',
                                'bg-info/10 text-info' => $ciclo->estado->value === 'planificado',
                                'bg-ink-faint/10 text-ink-faint' => $ciclo->estado->value === 'cerrado',
                            ])>
                                {{ $ciclo->estado->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                x-data
                                x-on:click="$dispatch('ver-ciclo', { cicloId: {{ $ciclo->id }} }); $dispatch('open-modal', 'ver-ciclo')"
                                class="text-sm font-medium text-accent hover:underline"
                            >Ver</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay grupos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $ciclos->links() }}</div>

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
            <h2 class="font-display text-lg text-ink">Nuevo grupo</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" placeholder="Ej. Grupo 1 - 2027" />
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
                        <x-input-label for="anio" value="Año" />
                        <x-text-input wire:model="anio" id="anio" type="number" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('anio')" class="mt-1" />
                    </div>
                </div>

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
                    <x-primary-button type="submit">Crear ciclo</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <livewire:academico.ciclos.ficha-modal wire:key="ficha-ciclo-modal" />
</div>
