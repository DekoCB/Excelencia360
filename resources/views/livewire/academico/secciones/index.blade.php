<?php

use App\Modules\Academico\Models\SeccionAcademica;
use App\Modules\Academico\Services\SeccionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $letra = '';

    public bool $activa = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $seccionId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $seccionId;

        if ($seccionId) {
            $seccion = SeccionAcademica::query()->findOrFail($seccionId);
            $this->letra = $seccion->letra;
            $this->activa = $seccion->activa;
        } else {
            $this->reset(['letra']);
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(SeccionService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'letra' => ['required', 'string', 'max:10', 'unique:secciones_academicas,letra,'.($this->editandoId ?? 'NULL').',id'],
        ]);

        if ($this->editandoId) {
            $service->actualizar(SeccionAcademica::query()->findOrFail($this->editandoId), [
                'letra' => $this->letra,
                'activa' => $this->activa,
            ]);
        } else {
            $service->crear(['letra' => $this->letra]);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Sección guardada correctamente.');
    }

    public function with(SeccionService $service): array
    {
        return [
            'secciones' => $service->todas(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Secciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Solo una etiqueta de reporte/filtro para organizar estudiantes dentro de un Programa de Estudio -- no afecta aulas ni horarios.</p>
    </x-slot>

    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nueva sección
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
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Letra</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($secciones as $seccion)
                    <tr wire:key="seccion-{{ $seccion->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $seccion->letra }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $seccion->activa, 'bg-ink-faint/10 text-ink-faint' => ! $seccion->activa])>
                                {{ $seccion->activa ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $seccion->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-ink-faint">No hay secciones registradas.</td></tr>
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
            <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar sección' : 'Nueva sección' }}</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="letra" value="Letra" />
                    <x-text-input wire:model="letra" id="letra" class="mt-1 block w-full" placeholder="Ej. F" />
                    <x-input-error :messages="$errors->get('letra')" class="mt-1" />
                </div>

                @if ($editandoId)
                    <label class="flex items-center gap-2 text-sm text-ink-dim">
                        <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                        Sección activa
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
