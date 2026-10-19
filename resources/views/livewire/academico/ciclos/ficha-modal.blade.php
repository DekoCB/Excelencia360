<?php

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
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

    #[On('ver-ciclo')]
    public function abrir(int $cicloId): void
    {
        Gate::authorize('academico.ver');

        $this->cicloId = $cicloId;
        $this->editando = false;
    }

    public function iniciarEdicion(): void
    {
        Gate::authorize('academico.gestionar');

        $ciclo = $this->ciclo();

        $this->resetValidation();
        $this->nombre = $ciclo->nombre;
        $this->tipo = $ciclo->tipo?->value ?? '';
        $this->anio = (string) $ciclo->anio;
        $this->fechaInicio = $ciclo->fecha_inicio->format('Y-m-d');
        $this->fechaFin = $ciclo->fecha_fin->format('Y-m-d');
        $this->estado = $ciclo->estado->value;
        $this->fechaInicioMatricula = $ciclo->fecha_inicio_matricula?->format('Y-m-d') ?? '';
        $this->fechaFinMatricula = $ciclo->fecha_fin_matricula?->format('Y-m-d') ?? '';
        $this->estadoMatricula = $ciclo->estado_matricula?->value ?? 'abierto';
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
    }

    public function guardarEdicion(CicloService $service): void
    {
        Gate::authorize('academico.gestionar');

        $ciclo = $this->ciclo();
        $esSeisMeses = $ciclo->modalidad === ModalidadCicloEnum::SEIS_MESES;

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

        $service->actualizar($ciclo, [
            'nombre' => $this->nombre,
            'modalidad' => $ciclo->modalidad,
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
            'horarios' => $ciclo?->horarios()->with(['curso', 'docente', 'aula', 'grado', 'dias'])->get(),
            'tiposCiclo' => TipoCicloEnum::cases(),
            'estadosCiclo' => EstadoCicloEnum::cases(),
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
            @else
                <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
            @endif
        </div>
    </x-modal>
</div>
