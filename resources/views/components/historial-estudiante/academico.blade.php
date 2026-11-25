@props(['historial', 'puedeExportar' => false, 'cicloLibreta' => null, 'cursosLibreta' => null, 'situacionFinalLibreta' => null])

<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <h2 class="text-sm font-semibold text-ink">Semestres cursados</h2>
    <div class="mt-4 divide-y divide-border">
        @forelse ($historial['matriculas'] as $matricula)
            <div class="flex items-center justify-between py-3 text-sm">
                <div>
                    <p class="text-ink">{{ $matricula->grado->nombre }} · {{ $matricula->ciclo->nombre }}</p>
                    <p class="text-ink-faint">
                        Matriculado el {{ $matricula->fecha_matricula->format('d/m/Y') }}
                        @if ($matricula->fecha_fin_estudio)
                            · Fin de estudios: {{ $matricula->fecha_fin_estudio->format('d/m/Y') }}
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
    <h2 class="text-sm font-semibold text-ink">Exámenes y notas</h2>

    @if ($historial['examenesUbicacion']->isNotEmpty())
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Exámenes de ubicación</h3>
        <div class="mt-2 divide-y divide-border">
            @foreach ($historial['examenesUbicacion'] as $examen)
                <div class="py-2 text-sm">
                    <p class="text-ink">{{ $examen->fecha->format('d/m/Y') }} · S/ {{ number_format((float) $examen->costo, 2) }}</p>
                    <p class="text-ink-faint">Resultado: {{ $examen->resultado ?? '—' }} @if ($examen->gradoAsignado) · Semestre asignado: {{ $examen->gradoAsignado->nombre }} @endif</p>
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
                <x-secondary-button type="button" wire:click="exportarLibretaPdf">Exportar boleta de notas (PDF)</x-secondary-button>
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
