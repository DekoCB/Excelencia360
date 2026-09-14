<?php

use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\GradoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $orden = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $gradoId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $gradoId;

        if ($gradoId) {
            $grado = Grado::query()->findOrFail($gradoId);
            $this->nombre = $grado->nombre;
            $this->orden = (string) $grado->orden;
            $this->activo = $grado->activo;
        } else {
            $this->reset(['nombre', 'orden']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(GradoService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:100',
            'orden' => 'required|integer|min:1|max:10',
        ]);

        if ($service->existeOrden((int) $this->orden, $this->editandoId)) {
            $this->addError('orden', "Ya existe un grado con el orden {$this->orden}.");

            return;
        }

        $datos = [
            'nombre' => $this->nombre,
            'orden' => (int) $this->orden,
        ];

        if ($this->editandoId) {
            $service->actualizar(Grado::query()->findOrFail($this->editandoId), [...$datos, 'activo' => $this->activo]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Grado guardado correctamente.');
    }

    public function with(GradoService $service): array
    {
        return [
            'grados' => $service->todos(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Grados</h1>
        <p class="mt-1 text-sm text-ink-dim">Niveles que cursan los estudiantes.</p>
    </x-slot>

    {{--
        El botón vive en el cuerpo del componente, no en x-slot="header": el
        layout renderiza ese slot fuera del wire:id del componente (en un
        <header> aparte), así que cualquier wire:click ahí queda huérfano y
        Livewire nunca recibe el clic. Ver commit que corrige este patrón.
    --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo grado
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
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Orden</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Aula</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($grados as $grado)
                    <tr wire:key="grado-{{ $grado->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $grado->nombre }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $grado->orden }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $grado->letraAula() }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $grado->activo, 'bg-ink-faint/10 text-ink-faint' => ! $grado->activo])>
                                {{ $grado->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $grado->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay grados registrados.</td></tr>
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
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar grado' : 'Nuevo grado' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="orden" value="Orden" />
                        <x-text-input wire:model="orden" id="orden" type="number" min="1" max="10" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('orden')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Grado activo
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
