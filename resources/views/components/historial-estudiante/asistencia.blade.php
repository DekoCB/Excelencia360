@props(['historial', 'puedeExportar' => false])

<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <div class="flex items-center justify-between gap-4">
        <h2 class="text-sm font-semibold text-ink">Asistencia</h2>
        @if ($puedeExportar)
            <x-secondary-button type="button" wire:click="exportarAsistenciaPdf">Exportar PDF</x-secondary-button>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-md bg-surface-2 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">Sesiones registradas</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ $historial['asistencia']['totalSesiones'] }}</p>
        </div>
        <div class="rounded-md bg-ok/10 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-ok">Asistencias</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ $historial['asistencia']['totalPresentes'] }}</p>
        </div>
        <div class="rounded-md bg-surface-2 p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">% Asistencia</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ $historial['asistencia']['porcentaje'] !== null ? $historial['asistencia']['porcentaje'].'%' : '—' }}</p>
        </div>
    </div>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Detalle</h3>
    <div class="mt-2 max-h-96 divide-y divide-border overflow-y-auto">
        @forelse ($historial['asistencia']['registros'] as $registro)
            <div class="flex items-center justify-between gap-4 py-2 text-sm">
                <span class="text-ink-dim">
                    {{ $registro->horario->curso->nombre }}
                    @if ($registro->horario->grado)
                        · {{ $registro->horario->grado->nombre }}
                    @endif
                    · {{ \Illuminate\Support\Carbon::parse($registro->fecha)->format('d/m/Y') }}
                </span>
                <span @class([
                    'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                    'bg-ok/10 text-ok' => $registro->estado->value === 'presente',
                    'bg-warn/10 text-warn' => in_array($registro->estado->value, ['tardanza', 'justificado'], true),
                    'bg-danger/10 text-danger' => $registro->estado->value === 'falta',
                ])>{{ $registro->estado->label() }}</span>
            </div>
        @empty
            <p class="py-4 text-sm text-ink-faint">Sin asistencias registradas.</p>
        @endforelse
    </div>
</div>
