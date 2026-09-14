@props(['porCiclo', 'miEstudianteId'])

{{--
    Notas del estudiante agrupadas por ciclo, con promedio general y
    detalle por curso/evaluación. La usa la sección "Mis evaluaciones"
    del dashboard del estudiante.
--}}
<div class="space-y-8">
    @forelse ($porCiclo as $grupo)
        <div>
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 class="font-display text-lg text-ink">{{ $grupo['ciclo']->nombre }}</h2>
                    @if ($grupo['promedioGeneral'] !== null)
                        <p class="text-xs text-ink-faint">Promedio general del ciclo: <span class="font-semibold text-accent">{{ number_format($grupo['promedioGeneral'], 2) }}</span></p>
                    @endif
                </div>
                <a
                    href="{{ route('evaluaciones.libreta', ['estudiante' => $miEstudianteId, 'ciclo' => $grupo['ciclo']->id]) }}"
                    wire:navigate
                    class="text-sm font-medium text-accent hover:underline"
                >
                    Descargar libreta →
                </a>
            </div>

            <div class="space-y-4">
                @foreach ($grupo['cursos'] as $item)
                    <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-ink">{{ $item['horario']->curso->nombre }}</p>
                                <p class="text-xs text-ink-faint">{{ $item['horario']->docente->name }}</p>
                            </div>
                            @if ($item['promedio'] !== null)
                                <div class="text-right">
                                    <p class="font-display text-xl text-ink">{{ number_format($item['promedio'], 2) }}</p>
                                    <span class="rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent">
                                        {{ \App\Modules\Evaluaciones\Enums\NotaLetraEnum::desde($item['promedio'])->value }}
                                    </span>
                                </div>
                            @else
                                <span class="shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-xs font-medium text-ink-dim">Sin notas aún</span>
                            @endif
                        </div>

                        @if ($item['calificaciones']->isNotEmpty())
                            <div class="mt-3 divide-y divide-border border-t border-border">
                                @foreach ($item['calificaciones'] as $calificacion)
                                    <div class="flex items-center justify-between gap-4 py-2 text-sm">
                                        <div>
                                            <p class="text-ink">{{ $calificacion->evaluacion->nombre }}</p>
                                            <p class="text-xs text-ink-faint">
                                                {{ $calificacion->evaluacion->fecha->format('d/m/Y') }}
                                                @if ($calificacion->observaciones)
                                                    · {{ $calificacion->observaciones }}
                                                @endif
                                            </p>
                                        </div>
                                        <p class="shrink-0 font-display text-ink">{{ number_format((float) $calificacion->nota_numerica, 2) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
            Todavía no tienes calificaciones registradas.
        </p>
    @endforelse
</div>
