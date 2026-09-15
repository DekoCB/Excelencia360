<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public string $estadoFiltro = '';

    public bool $mostrarWizard = false;

    // Búsqueda avanzada (§25 del prompt maestro): oculta por defecto para
    // no saturar el formulario simple de nombre/DNI + estado que cubre el
    // caso de uso más común.
    public bool $mostrarFiltrosAvanzados = false;

    public string $cicloFiltro = '';

    public string $gradoFiltro = '';

    public string $cursoFiltro = '';

    public string $docenteFiltro = '';

    public function mount(): void
    {
        Gate::authorize('matricula.ver');
    }

    #[On('wizard-cerrado')]
    public function cerrarWizard(): void
    {
        $this->mostrarWizard = false;
    }

    #[On('matricula-registrada')]
    public function matriculaRegistrada(int $estudianteId, string $nombre): void
    {
        $this->mostrarWizard = false;
        session()->flash('status', "Matrícula de {$nombre} registrada correctamente.");
        session()->flash('estudianteRegistradoId', $estudianteId);
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function updatingEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedCicloFiltro(): void
    {
        $this->gradoFiltro = '';
        $this->cursoFiltro = '';
        $this->resetPage();
    }

    public function updatedGradoFiltro(): void
    {
        $this->cursoFiltro = '';
        $this->resetPage();
    }

    public function updatingCursoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatingDocenteFiltro(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltrosAvanzados(): void
    {
        $this->reset(['cicloFiltro', 'gradoFiltro', 'cursoFiltro', 'docenteFiltro']);
        $this->resetPage();
    }

    public function with(MatriculaService $service): array
    {
        $estudiantes = $service->listarEstudiantes(
            $this->termino ?: null,
            $this->estadoFiltro ?: null,
            cicloId: $this->cicloFiltro !== '' ? (int) $this->cicloFiltro : null,
            gradoId: $this->gradoFiltro !== '' ? (int) $this->gradoFiltro : null,
            cursoId: $this->cursoFiltro !== '' ? (int) $this->cursoFiltro : null,
            docenteId: $this->docenteFiltro !== '' ? (int) $this->docenteFiltro : null,
        );

        return [
            'estudiantes' => $estudiantes,
            'sugerencias' => $estudiantes->take(6)->map(fn (Estudiante $estudiante) => [
                'value' => $estudiante->id,
                'label' => $estudiante->nombreCompleto(),
            ])->values()->all(),
            'estados' => EstadoEstudianteEnum::cases(),
            'ciclosDisponibles' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'gradosDisponibles' => Grado::query()->where('activo', true)->orderBy('orden')->get(),
            'cursosDisponibles' => $this->cursosDisponibles(),
            'docentesDisponibles' => Docente::query()->with('usuario')->get()->sortBy(fn (Docente $d) => $d->usuario->name)->values(),
        ];
    }

    /**
     * @return Collection<int, Curso>
     */
    private function cursosDisponibles(): Collection
    {
        if ($this->gradoFiltro === '') {
            return collect();
        }

        return Curso::query()
            ->where('grado_id', (int) $this->gradoFiltro)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Estudiantes</h1>
        <p class="mt-1 text-sm text-ink-dim">Estudiantes registrados y su estado.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('matricula.crear')
        <div class="mb-4 flex flex-wrap justify-end gap-3">
            <a href="{{ route('matricula.carga-masiva-estudiantes') }}" wire:navigate class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-4 py-2 font-display text-sm font-medium text-ink transition hover:bg-surface-2">
                <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                Carga masiva de estudiantes
            </a>
            <a href="{{ route('matricula.carga-masiva') }}" wire:navigate class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-4 py-2 font-display text-sm font-medium text-ink transition hover:bg-surface-2">
                <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                Matrícula masiva
            </a>
            <x-primary-button type="button" wire:click="$set('mostrarWizard', true)" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nueva matrícula
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4 flex items-center justify-between">
            <span>{{ session('status') }}</span>
            @if (session('estudianteRegistradoId'))
                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('ver-estudiante', { estudianteId: {{ session('estudianteRegistradoId') }} }); $dispatch('open-modal', 'ver-ficha')"
                    class="font-medium underline"
                >Ver ficha →</button>
            @endif
        </x-alert>
    @endif

    @if ($mostrarWizard)
        <livewire:matricula.wizard wire:key="wizard-nueva-matricula" />
    @endif

    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-buscador-combo
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por nombre, apellido o DNI…"
            :sugerencias="$sugerencias"
        />
        <x-select-input
            wire:model.live="estadoFiltro"
            class="w-full sm:max-w-xs"
            :options="collect($estados)->mapWithKeys(fn ($estado) => [$estado->value => $estado->label()])->prepend('Todos los estados', '')"
        />
        <button type="button" wire:click="$toggle('mostrarFiltrosAvanzados')" class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-accent hover:underline">
            <x-heroicon-o-adjustments-horizontal class="h-4 w-4" />
            Búsqueda avanzada
        </button>
    </div>

    @if ($mostrarFiltrosAvanzados)
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface-2 p-4">
            <div wire:key="ciclo-select-matricula">
                <x-input-label for="cicloFiltro" value="Grupo (periodo académico)" />
                <x-select-input
                    wire:model.live="cicloFiltro"
                    id="cicloFiltro"
                    class="mt-1 block w-56"
                    :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])->prepend('Todos los grupos', '')"
                />
            </div>
            <div wire:key="grado-select-matricula-{{ $cicloFiltro }}">
                <x-input-label for="gradoFiltro" value="Grado" />
                <x-select-input
                    wire:model.live="gradoFiltro"
                    id="gradoFiltro"
                    class="mt-1 block w-48"
                    :options="collect($gradosDisponibles)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Todos los grados', '')"
                />
            </div>
            <div wire:key="curso-select-matricula-{{ $gradoFiltro }}">
                <x-input-label for="cursoFiltro" value="Curso" />
                <x-select-input
                    wire:model.live="cursoFiltro"
                    id="cursoFiltro"
                    class="mt-1 block w-48"
                    :disabled="$gradoFiltro === ''"
                    :options="collect($cursosDisponibles)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre])->prepend('Todos los cursos', '')"
                />
            </div>
            <div wire:key="docente-select-matricula">
                <x-input-label for="docenteFiltro" value="Docente" />
                <x-select-input
                    wire:model.live="docenteFiltro"
                    id="docenteFiltro"
                    class="mt-1 block w-56"
                    :options="collect($docentesDisponibles)->mapWithKeys(fn ($docente) => [$docente->user_id => $docente->usuario->name])->prepend('Todos los docentes', '')"
                />
            </div>

            @if ($cicloFiltro !== '' || $gradoFiltro !== '' || $cursoFiltro !== '' || $docenteFiltro !== '')
                <x-secondary-button type="button" wire:click="limpiarFiltrosAvanzados">Limpiar filtros</x-secondary-button>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($estudiantes as $estudiante)
            <div wire:key="estudiante-{{ $estudiante->id }}" class="relative overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition hover:shadow-md">
                <span @class([
                    'absolute left-3 top-3 rounded-full px-2 py-0.5 text-xs font-medium',
                    'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                    'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
                ])>
                    {{ $estudiante->estado->label() }}
                </span>

                <div class="flex flex-col items-center gap-3 p-6 pt-10">
                    @if ($estudiante->fotoUrl())
                        <img src="{{ $estudiante->fotoUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-full object-cover">
                    @else
                        <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border border-dashed border-border bg-surface-2 text-ink-faint">
                            <x-heroicon-o-user class="h-8 w-8" />
                        </span>
                    @endif

                    <div class="text-center">
                        <p class="font-medium text-ink">{{ $estudiante->nombreCompleto() }}</p>
                        <p class="font-mono text-xs text-ink-faint">{{ $estudiante->dni }}</p>
                    </div>

                    <p class="text-xs text-ink-dim">{{ $estudiante->gradoActual?->nombre ?? '—' }}</p>
                </div>

                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('ver-estudiante', { estudianteId: {{ $estudiante->id }} }); $dispatch('open-modal', 'ver-ficha')"
                    class="block w-full border-t border-border px-4 py-3 text-center text-sm font-medium text-accent transition hover:bg-surface-2"
                >Ver ficha</button>
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No se encontraron estudiantes.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $estudiantes->links() }}</div>

    <livewire:matricula.ficha-modal wire:key="ficha-modal" />
</div>
