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
 * x-historial-estudiante.resumen).
 *
 * Dos modos, según el permiso de quien entra:
 * - Apoderado (solo matricula.ver_propio_hijo): sin buscador, acotado a
 *   los hijos vinculados a esta cuenta (Apoderado::user_id, ver
 *   MatriculaService::registrarApoderado()).
 * - Directorio de staff (reportes.historial_estudiante -- el mismo
 *   permiso que ya gobierna la búsqueda general en
 *   historial-estudiante/index.blade.php, así que no se concede ningún
 *   acceso nuevo): busca por apoderado o por su hijo, sobre todos los
 *   registros de Apoderado.
 *
 * $estudianteSeleccionadoId es una propiedad pública de Livewire: el
 * cliente puede intentar modificarla directamente (no solo a través de
 * seleccionarHijo()). Por eso cada punto que la usa -- seleccionarHijo(),
 * los dos export, y with() -- vuelve a validar contra idsPermitidos() en
 * vez de confiar en que llegó por el camino esperado (el directorio de
 * staff queda exento porque ya tiene acceso a cualquier estudiante).
 */
new #[Layout('layouts.app')] class extends Component
{
    public ?int $estudianteSeleccionadoId = null;

    public ?int $cicloLibretaId = null;

    public string $terminoBusqueda = '';

    public function mount(): void
    {
        abort_unless(
            $this->modoDirectorio() || Auth::user()->hasPermissionTo('matricula.ver_propio_hijo'),
            403
        );

        if ($this->modoDirectorio()) {
            return;
        }

        $hijos = $this->misHijos();
        abort_if($hijos->isEmpty(), 403);

        if ($hijos->count() === 1) {
            $this->estudianteSeleccionadoId = $hijos->first()->estudiante_id;
        }
    }

    public function seleccionarHijo(int $estudianteId): void
    {
        abort_unless($this->modoDirectorio() || in_array($estudianteId, $this->idsPermitidos(), true), 403);

        $this->estudianteSeleccionadoId = $estudianteId;
        $this->terminoBusqueda = '';

        // Igual criterio que historial-estudiante/index.blade.php: la
        // libreta filtrable arranca en el ciclo más reciente con notas.
        $historial = app(HistorialEstudianteService::class)->porId($estudianteId);
        $this->cicloLibretaId = $historial['notasPorCiclo']->last()['ciclo']->id ?? null;
    }

    public function exportarPdf(HistorialEstudianteService $servicio)
    {
        abort_unless(
            $this->estudianteSeleccionadoId !== null
                && ($this->modoDirectorio() || in_array($this->estudianteSeleccionadoId, $this->idsPermitidos(), true)),
            403
        );

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
        abort_unless(
            $this->estudianteSeleccionadoId !== null
                && ($this->modoDirectorio() || in_array($this->estudianteSeleccionadoId, $this->idsPermitidos(), true)),
            403
        );
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
        $modoDirectorio = $this->modoDirectorio();
        $hijos = $modoDirectorio ? collect() : $this->misHijos();
        $resultadosBusqueda = collect();

        if ($modoDirectorio) {
            if ($this->estudianteSeleccionadoId === null && $this->terminoBusqueda !== '') {
                $resultadosBusqueda = $this->buscarApoderados($this->terminoBusqueda);
            }
        } elseif ($this->estudianteSeleccionadoId !== null && ! in_array($this->estudianteSeleccionadoId, $hijos->pluck('estudiante_id')->all(), true)) {
            $this->estudianteSeleccionadoId = null;
        }

        $historial = $this->estudianteSeleccionadoId !== null ? $servicio->porId($this->estudianteSeleccionadoId) : null;

        $cicloLibreta = $historial ? $historial['matriculas']->first(fn ($matricula) => $matricula->ciclo_id === $this->cicloLibretaId)?->ciclo : null;
        $cursosLibreta = ($historial && $cicloLibreta) ? $libretas->resumenPorCursos($historial['estudiante'], $cicloLibreta) : collect();

        return [
            'modoDirectorio' => $modoDirectorio,
            'hijos' => $hijos,
            'resultadosBusqueda' => $resultadosBusqueda,
            'historial' => $historial,
            'cicloLibreta' => $cicloLibreta,
            'cursosLibreta' => $cursosLibreta,
            'situacionFinalLibreta' => $cursosLibreta->isNotEmpty() ? $libretas->calcularSituacionFinal($cursosLibreta) : null,
        ];
    }

    private function modoDirectorio(): bool
    {
        return Auth::user()->hasPermissionTo('reportes.historial_estudiante');
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
     * @return Collection<int, Apoderado>
     */
    private function buscarApoderados(string $termino): Collection
    {
        return Apoderado::query()
            ->with('estudiante')
            ->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%")
                    ->orWhereHas('estudiante', function ($sub) use ($termino) {
                        $sub->where('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%")
                            ->orWhere('dni', 'like', "%{$termino}%");
                    });
            })
            ->limit(8)
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
        <h1 class="font-display text-2xl text-ink">Tutores/Apoderados</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($modoDirectorio)
                Busca por apoderado o por su hijo para ver notas, pagos, documentos y situación académica.
            @else
                Notas, asistencia registrada en pagos, documentos y situación de pagos.
            @endif
        </p>
    </x-slot>

    <div class="space-y-6">
        @if ($modoDirectorio)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <x-input-label for="terminoBusqueda" value="Buscar tutor/apoderado o estudiante" />

                @if ($estudianteSeleccionadoId)
                    <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent sm:max-w-sm">
                        {{ $historial['estudiante']->nombreCompleto() ?? '' }}
                        <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
                    </div>
                @else
                    <x-text-input
                        wire:model.live.debounce.300ms="terminoBusqueda"
                        id="terminoBusqueda"
                        class="mt-1 block w-full sm:max-w-sm"
                        placeholder="Nombre, DNI del tutor o del estudiante…"
                        autocomplete="off"
                    />

                    @if ($resultadosBusqueda->isNotEmpty())
                        <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface sm:max-w-sm">
                            @foreach ($resultadosBusqueda as $apoderado)
                                <button
                                    type="button"
                                    wire:click="seleccionarHijo({{ $apoderado->estudiante_id }})"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                >
                                    {{ $apoderado->estudiante->nombreCompleto() }}
                                    <span class="text-xs text-ink-faint">· hijo(a) de {{ $apoderado->nombres }} ({{ $apoderado->parentesco }})</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif ($terminoBusqueda !== '')
                        <p class="mt-1 text-sm text-ink-faint">No se encontraron tutores/apoderados.</p>
                    @endif
                @endif
            </div>
        @elseif ($hijos->count() > 1)
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
        @elseif ($modoDirectorio)
            <p class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                Busca un tutor/apoderado o a su hijo para ver su información.
            </p>
        @else
            <p class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                Elige a uno de tus hijos para ver su información.
            </p>
        @endif
    </div>
</div>
