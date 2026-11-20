<?php

use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Academico\Services\ProgramaEstudioService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public bool $activo = true;

    public ?int $programaExpandidoId = null;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function alternarDetalle(int $programaId): void
    {
        $this->programaExpandidoId = $this->programaExpandidoId === $programaId ? null : $programaId;
    }

    public function abrirModal(?int $programaId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $programaId;

        if ($programaId) {
            $programa = ProgramaEstudio::query()->findOrFail($programaId);
            $this->nombre = $programa->nombre;
            $this->activo = $programa->activo;
        } else {
            $this->reset(['nombre']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(ProgramaEstudioService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:150',
        ]);

        if ($this->editandoId) {
            $service->actualizar(ProgramaEstudio::query()->findOrFail($this->editandoId), [
                'nombre' => $this->nombre,
                'activo' => $this->activo,
            ]);
        } else {
            $service->crear(['nombre' => $this->nombre]);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Programa de estudio guardado correctamente.');
    }

    public function with(ProgramaEstudioService $service): array
    {
        $programaExpandido = $this->programaExpandidoId ? ProgramaEstudio::query()->find($this->programaExpandidoId) : null;

        return [
            'programas' => $service->todos(),
            'seccionesDelExpandido' => $programaExpandido ? $service->seccionesConEstudiantes($programaExpandido) : collect(),
            'cursosDelExpandido' => $programaExpandido ? $service->cursosDe($programaExpandido) : collect(),
            'estudiantesDelExpandido' => $programaExpandido ? $service->estudiantesDe($programaExpandido) : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Programa de Estudio</h1>
        <p class="mt-1 text-sm text-ink-dim">Las carreras que ofrece la institución; cada una tiene sus propios semestres y cursos.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo programa de estudio
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
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Semestres</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($programas as $programa)
                    <tr wire:key="programa-{{ $programa->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $programa->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $programa->grados()->count() }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $programa->activo, 'bg-ink-faint/10 text-ink-faint' => ! $programa->activo])>
                                {{ $programa->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="alternarDetalle({{ $programa->id }})" class="text-sm font-medium text-accent hover:underline">
                                {{ $programaExpandidoId === $programa->id ? 'Ocultar detalle' : 'Ver detalle' }}
                            </button>
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $programa->id }})" class="ml-3 text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                    @if ($programaExpandidoId === $programa->id)
                        <tr wire:key="programa-detalle-{{ $programa->id }}" class="bg-surface-2">
                            <td colspan="4" class="px-4 py-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">Secciones con estudiantes</p>
                                        @forelse ($seccionesDelExpandido as $seccion)
                                            <div class="flex items-center justify-between border-b border-border py-1 text-sm last:border-b-0">
                                                <span class="text-ink">Sección {{ $seccion['letra'] }}</span>
                                                <span class="text-ink-faint">{{ $seccion['estudiantes'] }}</span>
                                            </div>
                                        @empty
                                            <p class="text-sm text-ink-faint">Sin estudiantes con sección asignada.</p>
                                        @endforelse
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">Unidades didácticas</p>
                                        @forelse ($cursosDelExpandido as $curso)
                                            <p class="border-b border-border py-1 text-sm text-ink last:border-b-0">{{ $curso->nombre }}</p>
                                        @empty
                                            <p class="text-sm text-ink-faint">Sin cursos registrados.</p>
                                        @endforelse
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">Estudiantes matriculados ({{ $estudiantesDelExpandido->count() }})</p>
                                        <div class="max-h-48 overflow-y-auto">
                                            @forelse ($estudiantesDelExpandido as $estudiante)
                                                @php $matricula = $estudiante->matriculas->first(); @endphp
                                                <div class="border-b border-border py-1 text-sm last:border-b-0">
                                                    <p class="text-ink">{{ $estudiante->nombreCompleto() }}</p>
                                                    <p class="text-xs text-ink-faint">
                                                        {{ $matricula?->grado?->nombre ?? '—' }}
                                                        @if ($matricula?->seccionAcademica)
                                                            · Sección {{ $matricula->seccionAcademica->letra }}
                                                        @endif
                                                    </p>
                                                </div>
                                            @empty
                                                <p class="text-sm text-ink-faint">Sin estudiantes matriculados.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-ink-faint">No hay programas de estudio registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar programa de estudio' : 'Nuevo programa de estudio' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" placeholder="Ej. Contabilidad" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Programa activo
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
