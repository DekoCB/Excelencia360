<?php

use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Directorio de apoderados para Coordinación/Dirección: crear y editar la
 * ficha de apoderado de un estudiante menor de edad -- hasta ahora esto
 * solo se podía hacer una vez, durante el asistente de matrícula o la
 * carga masiva (ver MatriculaService::registrarApoderado()), sin forma de
 * agregarlo o corregirlo después. Reutiliza el mismo método de servicio
 * para ambos casos: como Apoderado::estudiante_id es unique y
 * registrarApoderado() hace updateOrCreate(), crear sobre un estudiante
 * que ya tiene apoderado simplemente lo actualiza.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public ?string $estudianteNombreEnEdicion = null;

    public string $estudianteId = '';

    public string $nombres = '';

    public string $dni = '';

    public string $celular = '';

    public string $correo = '';

    public string $direccion = '';

    public string $parentesco = '';

    public function mount(): void
    {
        Gate::authorize('matricula.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('matricula.crear');

        $this->resetValidation();
        $this->editandoId = null;
        $this->estudianteNombreEnEdicion = null;
        $this->reset(['estudianteId', 'nombres', 'dni', 'celular', 'correo', 'direccion', 'parentesco']);
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $apoderadoId): void
    {
        Gate::authorize('matricula.editar');

        $apoderado = Apoderado::query()->with('estudiante')->findOrFail($apoderadoId);

        $this->resetValidation();
        $this->editandoId = $apoderado->id;
        $this->estudianteId = (string) $apoderado->estudiante_id;
        $this->estudianteNombreEnEdicion = $apoderado->estudiante->nombreCompleto();
        $this->nombres = $apoderado->nombres;
        $this->dni = $apoderado->dni;
        $this->celular = $apoderado->celular;
        $this->correo = (string) $apoderado->correo;
        $this->direccion = (string) $apoderado->direccion;
        $this->parentesco = $apoderado->parentesco;
        $this->mostrarModal = true;
    }

    public function guardar(MatriculaService $service): void
    {
        Gate::authorize($this->editandoId ? 'matricula.editar' : 'matricula.crear');

        $this->validate([
            'estudianteId' => 'required|integer|exists:estudiantes,id',
            'nombres' => 'required|string|max:150',
            'dni' => 'required|string|min:8|max:12',
            'celular' => 'required|string',
            'correo' => 'nullable|email|max:150',
            'direccion' => 'nullable|string|max:150',
            'parentesco' => 'required|string|max:50',
        ]);

        $estudiante = Estudiante::query()->findOrFail($this->estudianteId);

        $service->registrarApoderado($estudiante, new RegistrarApoderadoData(
            nombres: $this->nombres,
            dni: new Dni($this->dni),
            celular: new Telefono($this->celular),
            correo: $this->correo !== '' ? $this->correo : null,
            direccion: $this->direccion !== '' ? $this->direccion : null,
            parentesco: $this->parentesco,
        ));

        $this->mostrarModal = false;
        session()->flash('status', 'Apoderado guardado correctamente.');
    }

    public function with(MatriculaService $service): array
    {
        return [
            'apoderados' => $service->listarApoderados($this->termino !== '' ? $this->termino : null),
            'estudiantesDisponibles' => $this->editandoId ? collect() : $service->estudiantesSinApoderado(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Apoderados</h1>
        <p class="mt-1 text-sm text-ink-dim">Ficha del apoderado de cada estudiante menor de edad.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('matricula.crear')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModalCrear" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo apoderado
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <x-text-input wire:model.live.debounce.300ms="termino" type="search" class="mb-4 w-full sm:max-w-xs" placeholder="Buscar por nombre, DNI o hijo…" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($apoderados as $apoderado)
            <div wire:key="apoderado-{{ $apoderado->id }}" class="relative overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition hover:shadow-md">
                <div class="flex flex-col items-center gap-3 p-6">
                    <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border border-dashed border-border bg-surface-2 text-ink-faint">
                        <x-heroicon-o-user class="h-8 w-8" />
                    </span>

                    <div class="text-center">
                        <p class="font-medium text-ink">{{ $apoderado->nombres }}</p>
                        <p class="font-mono text-xs text-ink-faint">{{ $apoderado->dni }}</p>
                    </div>

                    <p class="text-xs text-ink-dim">{{ $apoderado->parentesco }} de {{ $apoderado->estudiante->nombreCompleto() }}</p>
                </div>

                @can('matricula.editar')
                    <button
                        type="button"
                        wire:click="abrirModalEditar({{ $apoderado->id }})"
                        class="block w-full border-t border-border px-4 py-3 text-center text-sm font-medium text-accent transition hover:bg-surface-2"
                    >
                        Editar
                    </button>
                @endcan
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No hay apoderados registrados todavía.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $apoderados->links() }}</div>

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
            class="w-full max-w-lg rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar apoderado' : 'Nuevo apoderado' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label value="Estudiante (hijo)" />
                        @if ($editandoId)
                            <p class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-ink-dim">{{ $estudianteNombreEnEdicion }}</p>
                        @else
                            <x-select-input
                                wire:model="estudianteId"
                                class="mt-1 block w-full"
                                placeholder="Selecciona un estudiante menor de edad"
                                :options="collect($estudiantesDisponibles)->mapWithKeys(fn ($estudiante) => [$estudiante->id => $estudiante->nombreCompleto().' · '.$estudiante->dni])"
                            />
                            <p class="mt-1 text-xs text-ink-faint">Solo aparecen los menores de edad que todavía no tienen un apoderado registrado.</p>
                        @endif
                        <x-input-error :messages="$errors->get('estudianteId')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="nombres" value="Nombres completos" />
                        <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="dni" value="DNI" />
                            <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="parentesco" value="Parentesco" />
                            <x-text-input wire:model="parentesco" id="parentesco" class="mt-1 block w-full" placeholder="Madre, padre, tío…" />
                            <x-input-error :messages="$errors->get('parentesco')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="celular" value="Celular" />
                            <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="correo" value="Correo (opcional)" />
                            <x-text-input wire:model="correo" id="correo" type="email" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('correo')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="direccion" value="Dirección (opcional)" />
                        <x-text-input wire:model="direccion" id="direccion" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('direccion')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
