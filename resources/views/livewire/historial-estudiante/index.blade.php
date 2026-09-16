<?php

use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Reportes\Services\HistorialEstudianteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public ?int $cicloLibretaId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.historial_estudiante'), 403);
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre, HistorialEstudianteService $servicio): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';

        // Por defecto, la libreta filtrable arranca en el ciclo más
        // reciente con notas -- sigue siendo editable desde el selector.
        $historial = $servicio->porId($estudianteId);
        $this->cicloLibretaId = $historial['notasPorCiclo']->last()['ciclo']->id ?? null;
    }

    public function exportarPdf(HistorialEstudianteService $servicio)
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.exportar'), 403);

        $historial = $this->estudianteSeleccionadoId !== null ? $servicio->porId($this->estudianteSeleccionadoId) : null;

        abort_if($historial === null, 404);

        // Ver reportes/index.blade.php: streamDownload() en vez de
        // Pdf::loadView(...)->download() para que Livewire reconozca la
        // respuesta como descarga de archivo.
        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.historial-estudiante', $historial)->output()),
            "historial-{$historial['estudiante']->dni}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Aparte del PDF general de historial: solo la libreta del ciclo
     * elegido en el selector, misma plantilla que ya usa
     * LibretaService::generar() para "Mi libreta"/la libreta que ve el
     * personal -- pero sin persistir un registro Libreta, ya que esto es
     * solo un export de lectura, no la generación oficial.
     */
    public function exportarLibretaPdf(HistorialEstudianteService $servicio, LibretaService $libretas)
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.exportar'), 403);
        abort_if($this->estudianteSeleccionadoId === null || $this->cicloLibretaId === null, 404);

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
        $resultadosBusqueda = collect();

        if ($this->terminoBusqueda !== '' && $this->estudianteSeleccionadoId === null) {
            $termino = $this->terminoBusqueda;

            $resultadosBusqueda = Estudiante::query()
                ->where(function ($query) use ($termino) {
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $historial = $this->estudianteSeleccionadoId !== null ? $servicio->porId($this->estudianteSeleccionadoId) : null;

        $cicloLibreta = $historial ? $historial['matriculas']->first(fn ($matricula) => $matricula->ciclo_id === $this->cicloLibretaId)?->ciclo : null;
        $cursosLibreta = ($historial && $cicloLibreta) ? $libretas->resumenPorCursos($historial['estudiante'], $cicloLibreta) : collect();

        return [
            'resultadosBusqueda' => $resultadosBusqueda,
            'historial' => $historial,
            'puedeExportar' => Auth::user()->hasPermissionTo('reportes.exportar'),
            'cicloLibreta' => $cicloLibreta,
            'cursosLibreta' => $cursosLibreta,
            'situacionFinalLibreta' => $cursosLibreta->isNotEmpty() ? $libretas->calcularSituacionFinal($cursosLibreta) : null,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Historial del estudiante</h1>
        <p class="mt-1 text-sm text-ink-dim">Busca por nombre o DNI para ver semestres cursados, pagos, documentos y notas en un solo lugar.</p>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <x-input-label for="terminoBusqueda" value="Buscar estudiante" />

            @if ($estudianteSeleccionadoId)
                <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent sm:max-w-sm">
                    {{ $estudianteSeleccionadoNombre }}
                    <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
                </div>
            @else
                <x-text-input
                    wire:model.live.debounce.300ms="terminoBusqueda"
                    id="terminoBusqueda"
                    class="mt-1 block w-full sm:max-w-sm"
                    placeholder="Nombre, apellido o DNI…"
                    autocomplete="off"
                />

                @if ($resultadosBusqueda->isNotEmpty())
                    <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface sm:max-w-sm">
                        @foreach ($resultadosBusqueda as $estudiante)
                            <button
                                type="button"
                                wire:click="seleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                            >
                                {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif ($terminoBusqueda !== '')
                    <p class="mt-1 text-sm text-ink-faint">No se encontraron estudiantes.</p>
                @endif
            @endif

            @if ($historial && $puedeExportar)
                <div class="mt-4">
                    <x-secondary-button type="button" wire:click="exportarPdf">Exportar PDF</x-secondary-button>
                </div>
            @endif
        </div>

        @if ($estudianteSeleccionadoId === null)
            <p class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                Busca un estudiante por nombre o DNI para ver su historial.
            </p>
        @elseif (! $historial)
            <p class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                No se encontró el estudiante seleccionado.
            </p>
        @else
            <x-historial-estudiante.resumen
                :historial="$historial"
                :puede-exportar="$puedeExportar"
                :ciclo-libreta="$cicloLibreta"
                :cursos-libreta="$cursosLibreta"
                :situacion-final-libreta="$situacionFinalLibreta"
            />
        @endif
    </div>
</div>
