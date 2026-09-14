<?php

use App\Modules\Docentes\Models\Docente;
use App\Modules\Docentes\Services\DocenteService;
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

    // Null mientras se crea uno nuevo; con valor, el modal edita ese docente.
    public ?int $docenteId = null;

    public string $nombres = '';

    public string $apellidos = '';

    public string $dni = '';

    public string $celular = '';

    public string $especialidad = '';

    public string $gradoAcademico = '';

    public string $fechaIngreso = '';

    public function mount(): void
    {
        Gate::authorize('docentes.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('docentes.gestionar');

        $this->reset(['docenteId', 'nombres', 'apellidos', 'dni', 'celular', 'especialidad', 'gradoAcademico', 'fechaIngreso']);
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $docenteId): void
    {
        Gate::authorize('docentes.gestionar');

        $docente = Docente::query()->findOrFail($docenteId);

        $this->reset(['nombres', 'apellidos', 'dni', 'celular']);
        $this->docenteId = $docente->id;
        $this->especialidad = $docente->especialidad ?? '';
        $this->gradoAcademico = $docente->grado_academico ?? '';
        $this->fechaIngreso = $docente->fecha_ingreso?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function guardar(DocenteService $service): void
    {
        Gate::authorize('docentes.gestionar');

        if ($this->docenteId === null) {
            $this->crear($service);

            return;
        }

        $this->validate([
            'especialidad' => 'nullable|string|max:150',
            'gradoAcademico' => 'nullable|string|max:150',
            'fechaIngreso' => 'nullable|date',
        ]);

        $service->actualizar(Docente::query()->findOrFail($this->docenteId), [
            'especialidad' => $this->especialidad !== '' ? $this->especialidad : null,
            'gradoAcademico' => $this->gradoAcademico !== '' ? $this->gradoAcademico : null,
            'fechaIngreso' => $this->fechaIngreso !== '' ? $this->fechaIngreso : null,
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Docente actualizado correctamente.');
    }

    private function crear(DocenteService $service): void
    {
        $this->validate([
            'nombres' => 'required|string|max:150',
            'apellidos' => 'required|string|max:150',
            'dni' => 'required|string|min:8|max:12',
            'celular' => 'nullable|string',
            'especialidad' => 'nullable|string|max:150',
            'gradoAcademico' => 'nullable|string|max:150',
            'fechaIngreso' => 'nullable|date',
        ]);

        if (! $service->dniDisponible($this->dni)) {
            $this->addError('dni', 'Ya existe una cuenta con este documento.');

            return;
        }

        $service->registrar([
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'dni' => new Dni($this->dni),
            'celular' => $this->celular !== '' ? new Telefono($this->celular) : null,
            'especialidad' => $this->especialidad !== '' ? $this->especialidad : null,
            'gradoAcademico' => $this->gradoAcademico !== '' ? $this->gradoAcademico : null,
            'fechaIngreso' => $this->fechaIngreso !== '' ? $this->fechaIngreso : null,
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Docente registrado correctamente.');
    }

    public function with(DocenteService $service): array
    {
        $docentes = $service->listar($this->termino ?: null);

        return [
            'docentes' => $docentes,
            'sugerencias' => $docentes->take(6)->map(fn (Docente $docente) => [
                'value' => $docente->id,
                'label' => $docente->usuario->name,
            ])->values()->all(),
            'puedeGestionar' => Gate::allows('docentes.gestionar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Docentes</h1>
        <p class="mt-1 text-sm text-ink-dim">Ficha de cada docente y su especialidad.</p>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-buscador-combo
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por nombre o DNI…"
            :sugerencias="$sugerencias"
        />

        @if ($puedeGestionar)
            <div class="flex gap-2">
                <a href="{{ route('docentes.carga-masiva') }}" wire:navigate>
                    <x-secondary-button type="button">Carga masiva</x-secondary-button>
                </a>
                <x-primary-button type="button" wire:click="abrirModalCrear" class="gap-2">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Nuevo docente
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
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Especialidad</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Grado académico</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Ingreso</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($docentes as $docente)
                    <tr wire:key="docente-{{ $docente->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $docente->usuario->name }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $docente->usuario->dni ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $docente->especialidad ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $docente->grado_academico ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $docente->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$docente->usuario->estado->value === 'activo' ? 'ok' : 'neutral'">
                                {{ ucfirst($docente->usuario->estado->value) }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($puedeGestionar)
                                <button type="button" wire:click="abrirModalEditar({{ $docente->id }})" class="text-sm font-medium text-accent hover:underline">
                                    Editar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-faint">No se encontraron docentes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $docentes->links() }}
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
            <h2 class="font-display text-lg text-ink">{{ $docenteId === null ? 'Nuevo docente' : 'Editar docente' }}</h2>

            <form wire:submit="guardar" class="mt-4 space-y-4">
                @if ($docenteId === null)
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

                    <p class="text-xs text-ink-faint">
                        El correo institucional y la contraseña inicial se asignan automáticamente a partir del DNI
                        (igual que en Usuarios). El nombre, DNI y celular se editan después desde Usuarios.
                    </p>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="especialidad" value="Especialidad" />
                        <x-text-input wire:model="especialidad" id="especialidad" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('especialidad')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gradoAcademico" value="Grado académico" />
                        <x-text-input wire:model="gradoAcademico" id="gradoAcademico" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('gradoAcademico')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="fechaIngreso" value="Fecha de ingreso" />
                    <x-date-input wire:model="fechaIngreso" id="fechaIngreso" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaIngreso')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                    <x-primary-button type="submit">{{ $docenteId === null ? 'Registrar' : 'Guardar cambios' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
