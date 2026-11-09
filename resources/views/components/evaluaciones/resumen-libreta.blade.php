@props(['cursos', 'situacionFinal' => null, 'puedeEditar' => false])

{{--
    Promedio por curso del estudiante en un ciclo, en formato tabla. La usan
    tanto "Mi libreta" (el estudiante viendo su propia libreta) como la
    libreta de un estudiante específico que consulta el personal.
    situacionFinal (ver LibretaService::calcularSituacionFinal()) es
    opcional: null mientras no haya cursos que evaluar. puedeEditar habilita
    el input de nota final para quien lo use (hoy solo
    evaluaciones/libreta.blade.php, que define notasEditadas/
    guardarNotasFinales()) -- cada fila se vuelve editable solo si además
    el usuario puede editar ESE horario (evaluaciones.editar-nota-final).
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
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Créditos</th>
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nota final</th>
                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            @forelse ($cursos as $curso)
                @php $puedeEditarFila = $puedeEditar && Auth::user()->can('evaluaciones.editar-nota-final', $curso['horario']); @endphp
                <tr>
                    <td class="px-4 py-3 text-ink">{{ $curso['nombre'] }}</td>
                    <td class="px-4 py-3 text-ink">{{ $curso['creditos'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-ink">
                        @if ($puedeEditarFila)
                            <input
                                type="number" step="0.01" min="0" max="20"
                                wire:model.defer="notasEditadas.{{ $curso['horario']->id }}"
                                class="w-20 rounded-md border-border text-sm focus:border-accent focus:ring-accent"
                            >
                        @else
                            {{ $curso['promedio'] !== null ? number_format($curso['promedio'], 2) : '—' }}
                        @endif
                        @if ($curso['editadaManualmente'])
                            <span class="ml-1 text-xs text-ink-faint" title="Editada a mano por el docente">✎</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($curso['estado'])
                            <x-badge :variant="$curso['estado'] === 'APROBADO' ? 'ok' : 'danger'">{{ $curso['estado'] }}</x-badge>
                        @else
                            <span class="text-ink-faint">—</span>
                        @endif
                    </td>
                </tr>
                <tr class="bg-surface-2">
                    <td colspan="4" class="px-4 py-3">
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
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-ink-faint">No hay cursos registrados para este ciclo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($puedeEditar && $cursos->isNotEmpty())
        <div class="border-t border-border px-4 py-3">
            <x-secondary-button type="button" wire:click="guardarNotasFinales" wire:loading.attr="disabled">
                Guardar notas finales
            </x-secondary-button>
        </div>
    @endif
</div>
