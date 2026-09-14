@props(['tareas'])

{{--
    Lista de tareas de un estudiante con su estado (pendiente/entregada/
    entregada tarde/vencida/calificada). La usa el detalle del día
    seleccionado en el calendario de tareas del dashboard.
--}}
<div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
    @forelse ($tareas as $tarea)
        @php
            $miEntrega = $tarea->entregas->first();
            $vencida = ! $miEntrega && $tarea->estaVencida();
        @endphp
        <a
            href="{{ route('aula-virtual.tarea', [$tarea->cursoVirtual, $tarea]) }}"
            wire:navigate
            class="flex items-center justify-between gap-4 px-4 py-3 text-sm transition hover:bg-surface-2"
        >
            <div>
                <p class="text-ink">{{ $tarea->titulo }}</p>
                <p class="mt-0.5 text-xs text-ink-faint">
                    {{ $tarea->cursoVirtual->horario->curso->nombre }} · Vence el {{ $tarea->fecha_limite->format('d/m/Y H:i') }}
                </p>
            </div>

            @if ($miEntrega?->estado === \App\Modules\AulaVirtual\Enums\EstadoEntregaEnum::CALIFICADO)
                <span class="shrink-0 rounded-full bg-ok/15 px-2 py-0.5 text-xs font-medium text-ok">
                    Calificada: {{ number_format((float) $miEntrega->nota, 1) }}/{{ $tarea->puntaje_max }}
                </span>
            @elseif ($miEntrega?->estado === \App\Modules\AulaVirtual\Enums\EstadoEntregaEnum::TARDE)
                <span class="shrink-0 rounded-full bg-warn/15 px-2 py-0.5 text-xs font-medium text-warn">Entregada tarde</span>
            @elseif ($miEntrega?->estado === \App\Modules\AulaVirtual\Enums\EstadoEntregaEnum::ENTREGADO)
                <span class="shrink-0 rounded-full bg-info/15 px-2 py-0.5 text-xs font-medium text-info">Entregada</span>
            @elseif ($vencida)
                <span class="shrink-0 rounded-full bg-danger/15 px-2 py-0.5 text-xs font-medium text-danger">Vencida</span>
            @else
                <span class="shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-xs font-medium text-ink-dim">Pendiente</span>
            @endif
        </a>
    @empty
        <p class="px-4 py-8 text-center text-sm text-ink-faint">No tienes tareas asignadas todavía.</p>
    @endforelse
</div>
