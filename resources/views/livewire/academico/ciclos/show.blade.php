<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Ciclo $ciclo;

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public function mount(Ciclo $ciclo): void
    {
        Gate::authorize('academico.ver');

        $this->ciclo = $ciclo;
    }

    public function crearPeriodo(CicloService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ]);

        $service->crearPeriodoMatricula($this->ciclo, $this->fechaInicio, $this->fechaFin);

        $this->reset(['fechaInicio', 'fechaFin']);
        session()->flash('status', 'Periodo de matrícula creado correctamente.');
    }

    public function with(): array
    {
        return [
            'periodos' => $this->ciclo->periodosMatricula()->latest('fecha_inicio')->get(),
            'horarios' => $this->ciclo->horarios()->with(['curso', 'docente', 'aula', 'grado', 'dias'])->get(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('academico.ciclos.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Grupos</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $ciclo->nombre }}</h1>
        <p class="mt-1 text-sm text-ink-dim">
            {{ $ciclo->modalidad->label() }}{{ $ciclo->tipo ? ' · '.$ciclo->tipo->label() : '' }} · {{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}
        </p>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <x-academico.detalle-ciclo :ciclo="$ciclo" :periodos="$periodos" :horarios="$horarios" />
</div>
