@props(['cursos', 'situacionFinal' => null])

{{--
    Promedio por curso del estudiante en un ciclo, en formato tabla. La usan
    tanto "Mi libreta" (el estudiante viendo su propia libreta) como la
    libreta de un estudiante específico que consulta el personal.
    situacionFinal (ver LibretaService::calcularSituacionFinal()) es
    opcional: null mientras no haya cursos que evaluar.
--}}
<div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
    @if ($situacionFinal)
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <h2 class="text-sm font-semibold text-ink">Situación final</h2>
            <x-badge :variant="$situacionFinal === 'APROBADO' ? 'ok' : 'danger'">{{ $situacionFinal }}</x-badge>
        </div>
    @endif

    <table class="min-w-full divide-y divide-border text-sm">
        <thead class="bg-surface-2">
            <tr>
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Curso</th>
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Promedio</th>
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Escala</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            @forelse ($cursos as $curso)
                <tr>
                    <td class="px-4 py-3 text-ink">{{ $curso['nombre'] }}</td>
                    <td class="px-4 py-3 text-ink">{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 2) : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($curso['letra'])
                            <span class="rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent">{{ $curso['letra'] }}</span>
                        @else
                            <span class="text-ink-faint">—</span>
                        @endif
                    </td>
                </tr>
                <tr class="bg-surface-2">
                    <td colspan="3" class="px-4 py-3">
                        @if (($curso['porMes'] ?? collect())->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($curso['porMes'] as $mes)
                                    <span class="rounded-full border border-border bg-surface px-2 py-1 text-xs text-ink-dim">
                                        {{ ucfirst($mes['mes']) }}: <span class="font-medium text-ink">{{ number_format($mes['promedio'], 2) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-ink-faint">Sin calificaciones registradas por mes.</p>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-ink-faint">No hay cursos registrados para este ciclo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
