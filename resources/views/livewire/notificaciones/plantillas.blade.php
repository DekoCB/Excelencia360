<?php

use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use App\Modules\Notificaciones\Services\PlantillaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $contenido = '';

    public bool $activa = true;

    public function mount(): void
    {
        Gate::authorize('whatsapp.enviar');
    }

    public function abrirModal(?int $plantillaId = null): void
    {
        $this->resetValidation();
        $this->editandoId = $plantillaId;

        if ($plantillaId) {
            $plantilla = PlantillaWhatsapp::query()->findOrFail($plantillaId);
            $this->nombre = $plantilla->nombre;
            $this->contenido = $plantilla->contenido;
            $this->activa = $plantilla->activa;
        } else {
            $this->reset(['nombre', 'contenido']);
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(PlantillaService $service): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'contenido' => 'required|string|max:1000',
        ]);

        if ($this->editandoId) {
            $service->actualizar(PlantillaWhatsapp::query()->findOrFail($this->editandoId), $this->nombre, $this->contenido, $this->activa);
        } else {
            $service->crear($this->nombre, $this->contenido, Auth::user());
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Plantilla guardada correctamente.');
    }

    public function with(PlantillaService $service): array
    {
        return [
            'plantillas' => $service->todas(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Plantillas de WhatsApp</h1>
        <p class="mt-1 text-sm text-ink-dim">Mensajes reutilizables para campañas y recordatorios. Usa @{{nombre}} para insertar el nombre del destinatario.</p>
    </x-slot>

    <div class="mb-4 flex justify-end">
        <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
            <x-heroicon-o-plus class="h-4 w-4" />
            Nueva plantilla
        </x-primary-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
        @forelse ($plantillas as $plantilla)
            <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm" wire:key="plantilla-{{ $plantilla->id }}">
                <div>
                    <p class="text-ink">
                        {{ $plantilla->nombre }}
                        @unless ($plantilla->activa)
                            <x-badge variant="neutral" class="ml-1">Inactiva</x-badge>
                        @endunless
                    </p>
                    <p class="mt-1 text-xs text-ink-faint">{{ $plantilla->contenido }}</p>
                </div>
                <button wire:click="abrirModal({{ $plantilla->id }})" class="shrink-0 text-sm font-medium text-accent hover:underline">Editar</button>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay plantillas registradas.</p>
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
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar plantilla' : 'Nueva plantilla' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="contenido" value="Contenido" />
                        <textarea wire:model="contenido" id="contenido" rows="4" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                        <p class="mt-1 text-xs text-ink-faint">Usa @{{nombre}} para insertar el nombre del destinatario.</p>
                        <x-input-error :messages="$errors->get('contenido')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                            Plantilla activa
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
