<?php

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Ciclo $ciclo;

    public bool $editando = false;

    public string $nombre = '';

    public string $tipo = '';

    public string $anio = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $estado = '';

    public string $fechaInicioMatricula = '';

    public string $fechaFinMatricula = '';

    public string $estadoMatricula = '';

    public function mount(Ciclo $ciclo): void
    {
        Gate::authorize('academico.ver');

        $this->ciclo = $ciclo;
    }

    public function iniciarEdicion(): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->nombre = $this->ciclo->nombre;
        $this->tipo = $this->ciclo->tipo?->value ?? '';
        $this->anio = (string) $this->ciclo->anio;
        $this->fechaInicio = $this->ciclo->fecha_inicio->format('Y-m-d');
        $this->fechaFin = $this->ciclo->fecha_fin->format('Y-m-d');
        $this->estado = $this->ciclo->estado->value;
        $this->fechaInicioMatricula = $this->ciclo->fecha_inicio_matricula?->format('Y-m-d') ?? '';
        $this->fechaFinMatricula = $this->ciclo->fecha_fin_matricula?->format('Y-m-d') ?? '';
        $this->estadoMatricula = $this->ciclo->estado_matricula?->value ?? 'abierto';
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
    }

    public function guardarEdicion(CicloService $service): void
    {
        Gate::authorize('academico.gestionar');

        $esSeisMeses = $this->ciclo->modalidad === ModalidadCicloEnum::SEIS_MESES;

        $this->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => $esSeisMeses ? 'required|string|in:'.implode(',', array_column(TipoCicloEnum::cases(), 'value')) : 'nullable',
            'anio' => 'required|integer|min:2020|max:2100',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
            'estado' => 'required|string|in:'.implode(',', array_column(EstadoCicloEnum::cases(), 'value')),
            'fechaInicioMatricula' => 'nullable|date',
            'fechaFinMatricula' => 'nullable|date',
        ]);

        $this->ciclo = $service->actualizar($this->ciclo, [
            'nombre' => $this->nombre,
            'modalidad' => $this->ciclo->modalidad,
            'tipo' => $esSeisMeses ? TipoCicloEnum::from($this->tipo) : null,
            'anio' => (int) $this->anio,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'estado' => EstadoCicloEnum::from($this->estado),
            'fecha_inicio_matricula' => $this->fechaInicioMatricula !== '' ? $this->fechaInicioMatricula : null,
            'fecha_fin_matricula' => $this->fechaFinMatricula !== '' ? $this->fechaFinMatricula : null,
            'estado_matricula' => $this->estadoMatricula,
        ]);

        $this->editando = false;
        session()->flash('status', 'Ciclo actualizado correctamente.');
    }

    public function with(): array
    {
        $this->ciclo->refresh();

        return [
            'horarios' => $this->ciclo->horarios()->with(['curso', 'docente', 'aula', 'grado', 'dias'])->get(),
            'tiposCiclo' => TipoCicloEnum::cases(),
            'estadosCiclo' => EstadoCicloEnum::cases(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('academico.ciclos.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Periodo Académico</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $ciclo->nombre }}</h1>
        <p class="mt-1 text-sm text-ink-dim">
            {{ $ciclo->modalidad->label() }}{{ $ciclo->tipo ? ' · '.$ciclo->tipo->label() : '' }} · {{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}
        </p>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <x-academico.detalle-ciclo
        :ciclo="$ciclo"
        :horarios="$horarios"
        :editando="$editando"
        :nombre="$nombre"
        :tipo="$tipo"
        :anio="$anio"
        :fecha-inicio="$fechaInicio"
        :fecha-fin="$fechaFin"
        :estado="$estado"
        :fecha-inicio-matricula="$fechaInicioMatricula"
        :fecha-fin-matricula="$fechaFinMatricula"
        :estado-matricula="$estadoMatricula"
        :tipos-ciclo="$tiposCiclo"
        :estados-ciclo="$estadosCiclo"
    />
</div>
