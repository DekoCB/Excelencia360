<?php

use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Directorio de estudiantes en estado "egresado" -- reutiliza
 * MatriculaService::listarEstudiantes() (mismo filtro por estado que ya
 * usa matricula/index) y el modal de ficha ya existente (con su "Editar
 * datos personales") para actualizar sus datos de contacto, en vez de
 * construir un formulario de edición nuevo.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public function mount(): void
    {
        Gate::authorize('egresados.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function with(MatriculaService $service): array
    {
        return [
            'egresados' => $service->listarEstudiantes($this->termino ?: null, 'egresado'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Egresados</h1>
        <p class="mt-1 text-sm text-ink-dim">Seguimiento de estudiantes egresados: consulta y actualiza sus datos de contacto.</p>
    </x-slot>

    <div class="mb-4">
        <x-text-input wire:model.live.debounce.300ms="termino" type="text" class="block w-full sm:w-80" placeholder="Buscar por nombre, apellido o DNI…" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($egresados as $estudiante)
            <div class="relative rounded-2xl border border-border bg-surface shadow-sm">
                <div class="flex flex-col items-center gap-3 p-6">
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

                    <p class="text-xs text-ink-dim">{{ $estudiante->celular ?: 'Sin celular registrado' }}</p>
                </div>

                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('ver-estudiante', { estudianteId: {{ $estudiante->id }} }); $dispatch('open-modal', 'ver-ficha')"
                    class="block w-full border-t border-border px-4 py-3 text-center text-sm font-medium text-accent transition hover:bg-surface-2"
                >Ver ficha</button>
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No se encontraron egresados.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $egresados->links() }}
    </div>

    <livewire:matricula.ficha-modal wire:key="ficha-modal" />
</div>
