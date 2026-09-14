<?php

use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
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

    public function with(MatriculaService $service): array
    {
        $estudiantes = $service->listarEstudiantes($this->termino ?: null, $this->estadoFiltro ?: null);

        return [
            'estudiantes' => $estudiantes,
            'sugerencias' => $estudiantes->take(6)->map(fn (Estudiante $estudiante) => [
                'value' => $estudiante->id,
                'label' => $estudiante->nombreCompleto(),
            ])->values()->all(),
            'estados' => EstadoEstudianteEnum::cases(),
        ];
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

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
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
    </div>

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
