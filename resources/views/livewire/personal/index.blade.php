<?php

use App\Modules\Personal\Models\Personal;
use App\Modules\Personal\Services\PersonalService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public bool $mostrarModal = false;

    // Null mientras se crea uno nuevo; con valor, el modal edita esa persona.
    public ?int $personalId = null;

    public string $nombres = '';

    public string $apellidos = '';

    public string $dni = '';

    public string $celular = '';

    public string $cargo = '';

    public string $area = '';

    public string $fechaIngreso = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('personal.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('personal.gestionar');

        $this->reset(['personalId', 'nombres', 'apellidos', 'dni', 'celular', 'cargo', 'area', 'fechaIngreso']);
        $this->activo = true;
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $personalId): void
    {
        Gate::authorize('personal.gestionar');

        $persona = Personal::query()->findOrFail($personalId);

        $this->personalId = $persona->id;
        $this->nombres = $persona->nombres;
        $this->apellidos = $persona->apellidos;
        $this->dni = $persona->dni;
        $this->celular = $persona->celular ?? '';
        $this->cargo = $persona->cargo;
        $this->area = $persona->area ?? '';
        $this->fechaIngreso = $persona->fecha_ingreso?->format('Y-m-d') ?? '';
        $this->activo = $persona->activo;
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function guardar(PersonalService $service): void
    {
        Gate::authorize('personal.gestionar');

        $this->validate([
            'nombres' => 'required|string|max:150',
            'apellidos' => 'required|string|max:150',
            'dni' => 'required|string|min:8|max:12',
            'celular' => 'nullable|string',
            'cargo' => 'required|string|max:150',
            'area' => 'nullable|string|max:150',
            'fechaIngreso' => 'nullable|date',
        ]);

        if (! $service->dniDisponible($this->dni, $this->personalId)) {
            $this->addError('dni', 'Ya existe una persona registrada con este documento.');

            return;
        }

        if ($this->personalId === null) {
            $service->registrar([
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'dni' => new Dni($this->dni),
                'celular' => $this->celular !== '' ? new Telefono($this->celular) : null,
                'cargo' => $this->cargo,
                'area' => $this->area !== '' ? $this->area : null,
                'fechaIngreso' => $this->fechaIngreso !== '' ? $this->fechaIngreso : null,
            ]);

            session()->flash('status', 'Persona registrada correctamente.');
        } else {
            $service->actualizar(Personal::query()->findOrFail($this->personalId), [
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'celular' => $this->celular !== '' ? new Telefono($this->celular) : null,
                'cargo' => $this->cargo,
                'area' => $this->area !== '' ? $this->area : null,
                'fechaIngreso' => $this->fechaIngreso !== '' ? $this->fechaIngreso : null,
                'activo' => $this->activo,
            ]);

            session()->flash('status', 'Datos actualizados correctamente.');
        }

        $this->mostrarModal = false;
    }

    public function with(PersonalService $service): array
    {
        $personas = $service->listar($this->termino ?: null);

        return [
            'personas' => $personas,
            'sugerencias' => $personas->take(6)->map(fn (Personal $persona) => [
                'value' => $persona->id,
                'label' => $persona->nombreCompleto(),
            ])->values()->all(),
            'puedeGestionar' => Gate::allows('personal.gestionar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Personal</h1>
        <p class="mt-1 text-sm text-ink-dim">Personal adicional de la institución: portería, limpieza, psicología y más.</p>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-buscador-combo
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por nombre o DNI…"
            :sugerencias="$sugerencias"
        />

        @if ($puedeGestionar)
            <div class="flex gap-2">
                <a href="{{ route('personal.carga-masiva') }}" wire:navigate>
                    <x-secondary-button type="button">Carga masiva</x-secondary-button>
                </a>
                <x-primary-button type="button" wire:click="abrirModalCrear" class="gap-2">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Nueva persona
                </x-primary-button>
            </div>
        @endif
    </div>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">DNI</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Cargo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Área</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Ingreso</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($personas as $persona)
                    <tr wire:key="personal-{{ $persona->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $persona->nombreCompleto() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $persona->dni }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $persona->cargo }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $persona->area ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $persona->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$persona->activo ? 'ok' : 'neutral'">
                                {{ $persona->activo ? 'Activo' : 'Inactivo' }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($puedeGestionar)
                                <button type="button" wire:click="abrirModalEditar({{ $persona->id }})" class="text-sm font-medium text-accent hover:underline">
                                    Editar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-faint">No se encontraron personas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $personas->links() }}
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
            <h2 class="font-display text-lg text-ink">{{ $personalId === null ? 'Nueva persona' : 'Editar persona' }}</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="nombres" value="Nombres" />
                        <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="apellidos" value="Apellidos" />
                        <x-text-input wire:model="apellidos" id="apellidos" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('apellidos')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="dni" value="DNI" />
                        <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="celular" value="Celular" />
                        <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="cargo" value="Cargo" />
                        <x-text-input wire:model="cargo" id="cargo" class="mt-1 block w-full" placeholder="Portero, psicóloga…" />
                        <x-input-error :messages="$errors->get('cargo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="area" value="Área (opcional)" />
                        <x-text-input wire:model="area" id="area" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('area')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="fechaIngreso" value="Fecha de ingreso" />
                    <x-date-input wire:model="fechaIngreso" id="fechaIngreso" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaIngreso')" class="mt-1" />
                </div>

                @if ($personalId !== null)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                        Sigue activo en la institución
                    </label>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                    <x-primary-button type="submit">{{ $personalId === null ? 'Registrar' : 'Guardar cambios' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
