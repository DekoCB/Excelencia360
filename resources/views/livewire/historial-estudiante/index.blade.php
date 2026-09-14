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
        <p class="mt-1 text-sm text-ink-dim">Busca por nombre o DNI para ver grados cursados, pagos, documentos y notas en un solo lugar.</p>
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
            @php $estudiante = $historial['estudiante']; @endphp

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-lg text-ink">{{ $estudiante->nombreCompleto() }}</h2>
                        <p class="text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->fecha_nacimiento->format('d/m/Y') }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                        'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
                    ])>
                        {{ $estudiante->estado->label() }}
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="text-sm font-semibold text-ink">Grados cursados</h2>
                <div class="mt-4 divide-y divide-border">
                    @forelse ($historial['matriculas'] as $matricula)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <div>
                                <p class="text-ink">{{ $matricula->grado->nombre }} · {{ $matricula->ciclo->nombre }} · {{ $matricula->ciclo->modalidad->label() }}</p>
                                <p class="text-ink-faint">
                                    Matriculado el {{ $matricula->fecha_matricula->format('d/m/Y') }}
                                    @if ($matricula->fecha_fin_estudio)
                                        · Fin de estudios: {{ $matricula->fecha_fin_estudio->format('d/m/Y') }}
                                    @endif
                                    @if ($matricula->siagieCompleto())
                                        · SIAGIE {{ $matricula->siagieCompleto() }}
                                    @endif
                                </p>
                            </div>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $matricula->estado->value === 'aprobada',
                                'bg-warn/10 text-warn' => in_array($matricula->estado->value, ['pendiente', 'observada'], true),
                                'bg-danger/10 text-danger' => $matricula->estado->value === 'anulada',
                            ])>
                                {{ $matricula->estado->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-ink-faint">Sin matrículas registradas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="text-sm font-semibold text-ink">Situación de pagos</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-md bg-ok/10 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-ok">Pagado</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPagado'], 2) }}</p>
                    </div>
                    <div class="rounded-md bg-warn/10 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-warn">Pendiente</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPendiente'], 2) }}</p>
                    </div>
                    <div class="rounded-md bg-surface-2 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">Exonerado</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalExonerado'], 2) }}</p>
                    </div>
                </div>

                @if ($historial['resumenPagos']['cuotasVencidas']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-danger">Cuotas vencidas</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['resumenPagos']['cuotasVencidas'] as $cuota)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-ink-dim">Cuota {{ $cuota->numero }} · {{ $cuota->planPago->matricula?->grado->nombre }} · {{ $cuota->planPago->matricula?->ciclo->nombre }}</span>
                                <span class="text-danger">S/ {{ number_format((float) $cuota->monto, 2) }} · venció {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($historial['resumenPagos']['cuotasPendientes']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-warn">Cuotas pendientes</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['resumenPagos']['cuotasPendientes'] as $cuota)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-ink-dim">Cuota {{ $cuota->numero }} · {{ $cuota->planPago->matricula?->grado->nombre }} · {{ $cuota->planPago->matricula?->ciclo->nombre }}</span>
                                <span class="text-warn">S/ {{ number_format($cuota->saldoPendiente(), 2) }} · vence {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($historial['resumenPagos']['cargosAdicionalesPendientes']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-warn">Cargos adicionales pendientes</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['resumenPagos']['cargosAdicionalesPendientes'] as $cargo)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-ink-dim">{{ $cargo->concepto }}</span>
                                <span class="text-warn">S/ {{ number_format($cargo->saldoPendiente(), 2) }} pendiente</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Detalle de pagos</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['pagos'] as $pago)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div>
                                <p class="text-ink">{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</p>
                                <p class="text-xs text-ink-faint">{{ $pago->fecha_pago->format('d/m/Y') }} · {{ $pago->medioPagoResumen() }}</p>
                                @if ($pago->partes->count() > 1)
                                    <p class="text-xs text-ink-faint">
                                        {{ $pago->partes->map(fn ($parte) => 'S/ '.number_format((float) $parte->monto, 2).' '.$parte->metodoConNota())->implode(' + ') }}
                                    </p>
                                @endif
                                @if ($pago->estado->value === 'rechazado' && $pago->motivo_rechazo)
                                    <p class="text-xs text-danger">{{ $pago->motivo_rechazo }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-ok/10 text-ok' => $pago->estado->value === 'aprobado',
                                    'bg-warn/10 text-warn' => $pago->estado->value === 'pendiente',
                                    'bg-danger/10 text-danger' => $pago->estado->value === 'rechazado',
                                ])>{{ $pago->estado->label() }}</span>
                                @if ($pago->recibo && $pago->recibo->getFirstMedia('pdf'))
                                    <a href="{{ $pago->recibo->getFirstMediaUrl('pdf') }}" target="_blank" class="block text-xs font-medium text-accent hover:underline">Recibo</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-ink-faint">Sin pagos registrados.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="text-sm font-semibold text-ink">Documentos</h2>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Subidos al matricularse</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['documentosSubidos'] as $documento)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $documento->tipo->label() }}</span>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $documento->verificado, 'bg-warn/10 text-warn' => ! $documento->verificado])>
                                {{ $documento->verificado ? 'Verificado' : 'Pendiente' }}
                            </span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han subido documentos.</p>
                    @endforelse
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Emitidos por la institución</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['documentosEmitidos'] as $certificado)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $certificado->tipo->label() }} · N.° {{ $certificado->numero }}</span>
                            <span class="text-ink-faint">{{ $certificado->fecha_emision->format('d/m/Y') }} · {{ $certificado->entregado_en ? 'Entregado' : 'Sin entregar' }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han emitido certificados ni constancias.</p>
                    @endforelse
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Libretas generadas</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['libretas'] as $libreta)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $libreta->ciclo->nombre }}</span>
                            <span class="text-ink-faint">{{ $libreta->generado_en?->format('d/m/Y') }} · {{ $libreta->entregado_en ? 'Entregada' : 'Sin entregar' }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han generado libretas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="text-sm font-semibold text-ink">Exámenes y notas</h2>

                @if ($historial['examenesUbicacion']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Exámenes de ubicación</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['examenesUbicacion'] as $examen)
                            <div class="py-2 text-sm">
                                <p class="text-ink">{{ $examen->fecha->format('d/m/Y') }} · S/ {{ number_format((float) $examen->costo, 2) }}</p>
                                <p class="text-ink-faint">Resultado: {{ $examen->resultado ?? '—' }} @if ($examen->gradoAsignado) · Grado asignado: {{ $examen->gradoAsignado->nombre }} @endif</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($historial['notasPorCiclo']->isNotEmpty())
                    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <x-input-label for="cicloLibretaId" value="Libreta de notas" />
                            <x-select-input
                                wire:model.live="cicloLibretaId"
                                id="cicloLibretaId"
                                class="mt-1 block w-full sm:w-64"
                                :options="collect($historial['notasPorCiclo'])->mapWithKeys(fn ($entrada) => [$entrada['ciclo']->id => $entrada['ciclo']->nombre])"
                            />
                        </div>
                        @if ($cicloLibreta && $puedeExportar)
                            <x-secondary-button type="button" wire:click="exportarLibretaPdf">Exportar libreta (PDF)</x-secondary-button>
                        @endif
                    </div>

                    @if ($cicloLibreta)
                        <div class="mt-2">
                            <x-evaluaciones.resumen-libreta :cursos="$cursosLibreta" :situacion-final="$situacionFinalLibreta" />
                        </div>
                    @endif
                @endif

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Notas por ciclo</h3>
                @forelse ($historial['notasPorCiclo'] as $entrada)
                    <p class="mt-3 text-sm font-medium text-ink">{{ $entrada['ciclo']->nombre }}</p>
                    <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach ($entrada['cursos'] as $curso)
                            <div class="flex items-center justify-between rounded-md bg-surface-2 px-3 py-1.5 text-xs">
                                <span class="text-ink-dim">{{ $curso['nombre'] }}</span>
                                <span class="text-ink">{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 1) : '—' }} @if ($curso['letra']) ({{ $curso['letra'] }}) @endif</span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="mt-2 text-sm text-ink-faint">Sin notas registradas todavía.</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
