<?php

use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\AulaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $capacidad = '';

    public string $ubicacion = '';

    public bool $activa = true;

    public string $cicloId = '';

    public string $letra = '';

    public string $cicloFiltro = '';

    public function mount(): void
    {
        Gate::authorize('academico.ver');

        $activo = Ciclo::query()->where('estado', 'activo')->first();
        $this->cicloFiltro = $activo ? (string) $activo->id : '';
    }

    public function abrirModal(?int $aulaId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $aulaId;

        if ($aulaId) {
            $aula = Aula::query()->findOrFail($aulaId);
            $this->nombre = $aula->nombre;
            $this->capacidad = (string) $aula->capacidad;
            $this->ubicacion = (string) $aula->ubicacion;
            $this->activa = $aula->activa;
            $this->cicloId = $aula->ciclo_id !== null ? (string) $aula->ciclo_id : '';
            $this->letra = $aula->letra ?? '';
        } else {
            $this->reset(['nombre', 'capacidad', 'ubicacion', 'cicloId', 'letra']);
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    /**
     * Al elegir grupo+letra, se sugiere el nombre "Aula {letra}. {grupo}"
     * -- el campo sigue editable a mano si el personal prefiere otro.
     */
    public function updatedCicloId(): void
    {
        $this->autocompletarNombre();
    }

    public function updatedLetra(): void
    {
        $this->autocompletarNombre();
    }

    private function autocompletarNombre(): void
    {
        if ($this->cicloId === '' || $this->letra === '') {
            return;
        }

        $ciclo = Ciclo::query()->find($this->cicloId);

        if ($ciclo) {
            $this->nombre = "Aula {$this->letra}. {$ciclo->nombre}";
        }
    }

    public function guardar(AulaService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:50',
            'capacidad' => 'required|integer|min:1|max:200',
            'ubicacion' => 'nullable|string|max:100',
            'cicloId' => 'nullable|integer|exists:ciclos,id',
            'letra' => 'nullable|string|in:A,B',
        ]);

        $datos = [
            'nombre' => $this->nombre,
            'capacidad' => (int) $this->capacidad,
            'ubicacion' => $this->ubicacion ?: null,
            'ciclo_id' => $this->cicloId !== '' ? (int) $this->cicloId : null,
            'letra' => $this->letra ?: null,
        ];

        if ($this->editandoId) {
            $service->actualizar(Aula::query()->findOrFail($this->editandoId), [...$datos, 'activa' => $this->activa]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Aula guardada correctamente.');
    }

    public function eliminar(AulaService $service, int $aulaId): void
    {
        Gate::authorize('academico.gestionar');

        try {
            $service->eliminar(Aula::query()->findOrFail($aulaId));
            session()->flash('status', 'Aula eliminada correctamente.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->validator->errors()->first());
        }
    }

    public function with(AulaService $service): array
    {
        return [
            'aulas' => $service->todas(),
            'ciclos' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'ocupacion' => $this->cicloFiltro ? $service->ocupacion((int) $this->cicloFiltro) : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Aulas</h1>
        <p class="mt-1 text-sm text-ink-dim">Espacios físicos disponibles para dictar clases.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nueva aula
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">{{ session('error') }}</div>
    @endif

    <div class="mb-8">
        <h2 class="mb-2 font-display text-lg text-ink">Ocupación por turno</h2>
        <p class="mb-3 text-sm text-ink-dim">Estudiantes matriculados por aula en cada turno (Lunes-Miércoles, Martes-Jueves, Domingo), frente a su capacidad.</p>

        <x-select-input
            wire:model.live="cicloFiltro"
            placeholder="Selecciona un ciclo…"
            class="mb-4 w-full sm:max-w-xs"
            :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
        />

        @if (! $cicloFiltro)
            <div class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                Selecciona un ciclo para ver la ocupación de las aulas.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($ocupacion as $fila)
                    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        <div class="flex items-center justify-between border-b border-border bg-surface-2 px-4 py-2">
                            <span class="font-display text-sm text-ink">{{ $fila['aula']->nombre }}</span>
                            <span class="text-xs text-ink-faint">Capacidad {{ $fila['aula']->capacidad }}</span>
                        </div>

                        @if ($fila['porFranja']->isEmpty())
                            <p class="px-4 py-6 text-center text-sm text-ink-faint">Sin horarios asignados este ciclo.</p>
                        @else
                            <div class="divide-y divide-border">
                                @foreach ($fila['porFranja'] as $grupoFranja)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $grupoFranja['label'] }}</span>
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-danger/10 text-danger' => $grupoFranja['totalEstudiantes'] > $fila['aula']->capacidad,
                                                'bg-ok/10 text-ok' => $grupoFranja['totalEstudiantes'] <= $fila['aula']->capacidad,
                                            ])>
                                                {{ $grupoFranja['totalEstudiantes'] }} / {{ $fila['aula']->capacidad }}
                                            </span>
                                        </div>
                                        <div class="mt-1 space-y-0.5">
                                            @foreach ($grupoFranja['horarios'] as $item)
                                                <p class="text-xs text-ink-dim">
                                                    {{ $item['horario']->curso->nombre }} · {{ $item['horario']->grado->nombre }}
                                                    <span class="text-ink-faint">— {{ $item['estudiantes'] }} estudiante{{ $item['estudiantes'] === 1 ? '' : 's' }}</span>
                                                </p>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <h2 class="mb-3 font-display text-lg text-ink">Aulas registradas</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($aulas as $aula)
            <div wire:key="aula-{{ $aula->id }}" class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-display text-lg text-ink">{{ $aula->nombre }}</p>
                        <p class="text-sm text-ink-dim">{{ $aula->ubicacion ?? 'Sin ubicación' }}</p>
                    </div>
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $aula->activa, 'bg-ink-faint/10 text-ink-faint' => ! $aula->activa])>
                        {{ $aula->activa ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-ink-faint">Capacidad: <span class="text-ink">{{ $aula->capacidad }}</span> estudiantes</p>
                @can('academico.gestionar')
                    <div class="mt-3 flex items-center gap-3">
                        <button wire:click="abrirModal({{ $aula->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                        <button
                            type="button"
                            x-on:click="$store.confirm.preguntar(@js('¿Eliminar «'.$aula->nombre.'»? Esta acción no se puede deshacer.'), () => $wire.eliminar({{ $aula->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })"
                            class="text-sm font-medium text-danger hover:underline"
                        >
                            Eliminar
                        </button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No hay aulas registradas.</p>
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
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar aula' : 'Nueva aula' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cicloId" value="Grupo (opcional)" />
                            <x-select-input
                                wire:model.live="cicloId"
                                id="cicloId"
                                class="mt-1 block w-full"
                                placeholder="Aula suelta, sin grupo"
                                :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                            />
                            <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="letra" value="Letra (opcional)" />
                            <x-select-input
                                wire:model.live="letra"
                                id="letra"
                                class="mt-1 block w-full"
                                placeholder="Sin letra"
                                :options="['A' => 'Aula A (grados 1-2)', 'B' => 'Aula B (grados 3-4)']"
                            />
                            <x-input-error :messages="$errors->get('letra')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="capacidad" value="Capacidad" />
                            <x-text-input wire:model="capacidad" id="capacidad" type="number" min="1" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('capacidad')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ubicacion" value="Ubicación" />
                            <x-text-input wire:model="ubicacion" id="ubicacion" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ubicacion')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                            Aula activa
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
