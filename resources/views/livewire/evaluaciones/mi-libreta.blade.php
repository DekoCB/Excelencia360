<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Estudiante $estudiante;

    public string $cicloId = '';

    public bool $estaBloqueado = false;

    public function mount(BloqueoAccesoService $bloqueos): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('evaluaciones.ver_propio') && $user->estudiante, 403);

        $this->estudiante = $user->estudiante;

        $ultimoCiclo = $this->ciclosMatriculados()->first();
        $this->cicloId = $ultimoCiclo ? (string) $ultimoCiclo->id : '';

        $this->estaBloqueado = $bloqueos->estaBloqueado($this->estudiante)
            || $bloqueos->tieneCuotasVencidasEnCicloActual($this->estudiante);
    }

    public function generar(LibretaService $service): void
    {
        abort_if($this->estaBloqueado, 403);

        $ciclo = Ciclo::query()->findOrFail($this->cicloId);

        $service->generar($this->estudiante, $ciclo);
    }

    /**
     * @return Collection<int, Ciclo>
     */
    public function ciclosMatriculados(): Collection
    {
        return Ciclo::query()
            ->whereIn('id', Matricula::query()
                ->where('estudiante_id', $this->estudiante->id)
                ->where('estado', 'aprobada')
                ->pluck('ciclo_id'))
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    public function with(LibretaService $service): array
    {
        $ciclo = $this->cicloId !== '' ? Ciclo::query()->find($this->cicloId) : null;

        if (! $ciclo) {
            return ['ciclo' => null, 'libreta' => null, 'urlPdf' => null, 'cursos' => collect(), 'situacionFinal' => null];
        }

        $libreta = Libreta::query()
            ->where('estudiante_id', $this->estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->first();

        $cursos = $service->resumenPorCursos($this->estudiante, $ciclo);

        return [
            'ciclo' => $ciclo,
            'libreta' => $libreta,
            'urlPdf' => $libreta?->getFirstMediaUrl('pdf'),
            'cursos' => $cursos,
            'situacionFinal' => $cursos->isNotEmpty() ? $service->calcularSituacionFinal($cursos) : null,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mi libreta</h1>
        <p class="mt-1 text-sm text-ink-dim">{{ $estudiante->nombreCompleto() }}</p>
    </x-slot>

    @if (! $ciclo)
        <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
            Todavía no tienes una matrícula aprobada en ningún ciclo.
        </p>
    @else
        @if ($this->ciclosMatriculados()->count() > 1)
            <div class="mb-4 max-w-xs">
                <x-input-label for="cicloId" value="Ciclo" />
                <x-select-input
                    wire:model.live="cicloId"
                    id="cicloId"
                    class="mt-1 block w-full"
                    :options="$this->ciclosMatriculados()->mapWithKeys(fn ($c) => [$c->id => $c->nombre])"
                />
            </div>
        @endif

        @if ($estaBloqueado)
            <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-6 text-sm text-danger">
                <p class="font-medium">Tu libreta no está disponible.</p>
                <p class="mt-1">Tienes cuotas vencidas sin pagar. Regulariza tu deuda en
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                    o comunícate con Cobranza para un compromiso de pago.</p>
            </div>
        @else
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                @if ($libreta && $libreta->generado_en)
                    <p class="text-sm text-ink-dim">
                        Última libreta generada el {{ $libreta->generado_en->format('d/m/Y H:i') }}.
                    </p>

                    @if ($urlPdf)
                        <a href="{{ $urlPdf }}" target="_blank" class="mt-3 inline-block text-sm font-medium text-accent hover:underline">
                            Descargar PDF →
                        </a>
                    @endif
                @else
                    <p class="text-sm text-ink-faint">Todavía no se ha generado una libreta para este ciclo.</p>
                @endif

                <div class="mt-4">
                    <x-primary-button type="button" wire:click="generar" wire:loading.attr="disabled">
                        {{ $libreta ? 'Actualizar libreta' : 'Generar libreta' }}
                    </x-primary-button>
                </div>
            </div>

            <div class="mt-6">
                <x-evaluaciones.resumen-libreta :cursos="$cursos" :situacion-final="$situacionFinal" />
            </div>
        @endif
    @endif
</div>
