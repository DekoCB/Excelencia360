<?php

use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Reportes\Services\HistorialEstudianteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Portal de Apoderados: mismo resumen de solo lectura que ya usa
 * Coordinación/Dirección en historial-estudiante/index.blade.php (ver
 * x-historial-estudiante.resumen), pero sin buscador -- acotado a los
 * hijos vinculados a esta cuenta (Apoderado::user_id, ver
 * MatriculaService::registrarApoderado()).
 *
 * $estudianteSeleccionadoId es una propiedad pública de Livewire: el
 * cliente puede intentar modificarla directamente (no solo a través de
 * seleccionarHijo()). Por eso cada punto que la usa -- seleccionarHijo(),
 * los dos export, y with() -- vuelve a validar contra idsPermitidos() en
 * vez de confiar en que llegó por el camino esperado.
 */
new #[Layout('layouts.app')] class extends Component
{
    public ?int $estudianteSeleccionadoId = null;

    public ?int $cicloLibretaId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('matricula.ver_propio_hijo'), 403);

        $hijos = $this->misHijos();
        abort_if($hijos->isEmpty(), 403);

        if ($hijos->count() === 1) {
            $this->estudianteSeleccionadoId = $hijos->first()->estudiante_id;
        }
    }

    public function seleccionarHijo(int $estudianteId): void
    {
        abort_unless(in_array($estudianteId, $this->idsPermitidos(), true), 403);

        $this->estudianteSeleccionadoId = $estudianteId;

        // Igual criterio que historial-estudiante/index.blade.php: la
        // libreta filtrable arranca en el ciclo más reciente con notas.
        $historial = app(HistorialEstudianteService::class)->porId($estudianteId);
        $this->cicloLibretaId = $historial['notasPorCiclo']->last()['ciclo']->id ?? null;
    }

    public function exportarPdf(HistorialEstudianteService $servicio)
    {
        abort_unless($this->estudianteSeleccionadoId !== null && in_array($this->estudianteSeleccionadoId, $this->idsPermitidos(), true), 403);

        $historial = $servicio->porId($this->estudianteSeleccionadoId);
        abort_if($historial === null, 404);

        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.historial-estudiante', $historial)->output()),
            "historial-{$historial['estudiante']->dni}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function exportarLibretaPdf(HistorialEstudianteService $servicio, LibretaService $libretas)
    {
        abort_unless($this->estudianteSeleccionadoId !== null && in_array($this->estudianteSeleccionadoId, $this->idsPermitidos(), true), 403);
        abort_if($this->cicloLibretaId === null, 404);

        $historial = $servicio->porId($this->estudianteSeleccionadoId);
        abort_if($historial === null, 404);

        $ciclo = $historial['matriculas']->first(fn ($matricula) => $matricula->ciclo_id === $this->cicloLibretaId)?->ciclo;
        abort_if($ciclo === null, 404);

        $estudiante = $historial['estudiante'];

        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.libreta', $libretas->datosParaPdf($estudiante, $ciclo))->output()),
            "libreta-{$estudiante->dni}-{$ciclo->anio}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function with(HistorialEstudianteService $servicio, LibretaService $libretas): array
    {
        $hijos = $this->misHijos();
        $idsPermitidos = $hijos->pluck('estudiante_id')->all();

        if ($this->estudianteSeleccionadoId !== null && ! in_array($this->estudianteSeleccionadoId, $idsPermitidos, true)) {
            $this->estudianteSeleccionadoId = null;
        }

        $historial = $this->estudianteSeleccionadoId !== null ? $servicio->porId($this->estudianteSeleccionadoId) : null;

        $cicloLibreta = $historial ? $historial['matriculas']->first(fn ($matricula) => $matricula->ciclo_id === $this->cicloLibretaId)?->ciclo : null;
        $cursosLibreta = ($historial && $cicloLibreta) ? $libretas->resumenPorCursos($historial['estudiante'], $cicloLibreta) : collect();

        return [
            'hijos' => $hijos,
            'historial' => $historial,
            'cicloLibreta' => $cicloLibreta,
            'cursosLibreta' => $cursosLibreta,
            'situacionFinalLibreta' => $cursosLibreta->isNotEmpty() ? $libretas->calcularSituacionFinal($cursosLibreta) : null,
        ];
    }

    /**
     * @return Collection<int, Apoderado>
     */
    private function misHijos(): Collection
    {
        return Apoderado::query()
            ->where('user_id', Auth::id())
            ->with('estudiante')
            ->get();
    }

    /**
     * @return list<int>
     */
    private function idsPermitidos(): array
    {
        return $this->misHijos()->pluck('estudiante_id')->all();
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis hijos</h1>
        <p class="mt-1 text-sm text-ink-dim">Notas, asistencia registrada en pagos, documentos y situación de pagos.</p>
    </x-slot>

    <div class="space-y-6">
        @if ($hijos->count() > 1)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <p class="text-sm font-semibold text-ink">Elige a quién ver</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($hijos as $apoderado)
                        <button
                            type="button"
                            wire:click="seleccionarHijo({{ $apoderado->estudiante_id }})"
                            @class([
                                'rounded-full border px-4 py-2 text-sm transition',
                                'border-accent bg-accent-soft text-accent font-semibold' => $estudianteSeleccionadoId === $apoderado->estudiante_id,
                                'border-border text-ink-dim hover:bg-surface-2' => $estudianteSeleccionadoId !== $apoderado->estudiante_id,
                            ])
                        >
                            {{ $apoderado->estudiante->nombreCompleto() }}
                            <span class="text-xs text-ink-faint">· {{ $apoderado->parentesco }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($historial)
            <div class="flex justify-end">
                <x-secondary-button type="button" wire:click="exportarPdf">Exportar PDF</x-secondary-button>
            </div>

            <x-historial-estudiante.resumen
                :historial="$historial"
                :puede-exportar="true"
                :ciclo-libreta="$cicloLibreta"
                :cursos-libreta="$cursosLibreta"
                :situacion-final-libreta="$situacionFinalLibreta"
            />
        @else
            <p class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                Elige a uno de tus hijos para ver su información.
            </p>
        @endif
    </div>
</div>
