@props(['estudiante'])

<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-display text-lg text-ink">{{ $estudiante->nombreCompleto() }}</h2>
            <p class="text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->fecha_nacimiento?->format('d/m/Y') ?? 'Fecha de nacimiento no registrada' }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
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
