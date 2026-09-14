<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Modal "Ver" de academico/ciclos/index.blade.php, aislado en su propio
 * componente para que abrirlo no dispare una re-consulta de la lista
 * completa de ciclos.
 */
new class extends Component
{
    public ?int $cicloId = null;

    public string $fechaInicio = '';

    public string $fechaFin = '';

    #[On('ver-ciclo')]
    public function abrir(int $cicloId): void
    {
        Gate::authorize('academico.ver');

        $this->cicloId = $cicloId;
        $this->reset(['fechaInicio', 'fechaFin']);
    }

    public function crearPeriodo(CicloService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ]);

        $service->crearPeriodoMatricula($this->ciclo(), $this->fechaInicio, $this->fechaFin);

        $this->reset(['fechaInicio', 'fechaFin']);
    }

    private function ciclo(): Ciclo
    {
        return Ciclo::query()->findOrFail($this->cicloId);
    }

    public function with(): array
    {
        $ciclo = $this->cicloId ? Ciclo::query()->find($this->cicloId) : null;

        return [
            'ciclo' => $ciclo,
            'periodos' => $ciclo?->periodosMatricula()->latest('fecha_inicio')->get(),
            'horarios' => $ciclo?->horarios()->with(['curso', 'docente', 'aula', 'grado', 'dias'])->get(),
        ];
    }
}; ?>

<div>
    <x-modal name="ver-ciclo" :tv="true" max-width="2xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <div>
                <h2 class="font-display text-lg text-ink">{{ $ciclo?->nombre ?? 'Ciclo' }}</h2>
                @if ($ciclo)
                    <p class="text-sm text-ink-dim">{{ $ciclo->modalidad->label() }}{{ $ciclo->tipo ? ' · '.$ciclo->tipo->label() : '' }} · {{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}</p>
                @endif
            </div>
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="max-h-[75vh] overflow-y-auto p-6" wire:loading.class="opacity-50">
            @if ($ciclo)
                <x-academico.detalle-ciclo :ciclo="$ciclo" :periodos="$periodos" :horarios="$horarios" />
            @else
                <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
            @endif
        </div>
    </x-modal>
</div>
