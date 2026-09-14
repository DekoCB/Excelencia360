@props(['ciclo', 'periodos', 'horarios'])

{{--
    Contenido del detalle de ciclo, compartido entre la página completa
    (academico/ciclos/show.blade.php, para acceso directo por URL) y el
    modal "Ver" de academico/ciclos/index.blade.php. El wire:submit de
    "Crear periodo" llama al componente Livewire que envuelve este parcial,
    así que ambos (show y el modal) deben exponer crearPeriodo(),
    $fechaInicio y $fechaFin.
--}}
<div class="space-y-6">
    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Periodos de matrícula</h2>
        <p class="mt-1 text-sm text-ink-dim">
            La ventana de matrícula debe caer entre 30 días antes del inicio del ciclo y su fecha de cierre.
        </p>

        <div class="mt-4 divide-y divide-border">
            @forelse ($periodos as $periodo)
                <div class="flex items-center justify-between py-3 text-sm">
                    <span class="text-ink">{{ $periodo->fecha_inicio->format('d/m/Y') }} – {{ $periodo->fecha_fin->format('d/m/Y') }}</span>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-ok/10 text-ok' => $periodo->estado->value === 'abierto',
                        'bg-ink-faint/10 text-ink-faint' => $periodo->estado->value === 'cerrado',
                    ])>
                        {{ $periodo->estado->label() }}
                    </span>
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no hay periodos de matrícula para este ciclo.</p>
            @endforelse
        </div>

        @can('academico.gestionar')
            <form wire:submit="crearPeriodo" class="mt-4 flex items-end gap-3 border-t border-border pt-4">
                <div class="flex-1">
                    <x-input-label for="fechaInicio" value="Apertura" />
                    <x-date-input wire:model="fechaInicio" id="fechaInicio" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                </div>
                <div class="flex-1">
                    <x-input-label for="fechaFin" value="Cierre" />
                    <x-date-input wire:model="fechaFin" id="fechaFin" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                </div>
                <x-primary-button type="submit">Crear periodo</x-primary-button>
            </form>
        @endcan
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Horarios de este ciclo</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($horarios as $horario)
                <div class="py-3 text-sm">
                    <p class="text-ink">{{ $horario->curso->nombre }} · {{ $horario->grado->nombre }}</p>
                    <p class="text-ink-faint">{{ $horario->docente->name }} · {{ $horario->aula->nombre }} · {{ $horario->diasResumen() }}</p>
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no hay horarios asignados a este ciclo.</p>
            @endforelse
        </div>
        @can('academico.gestionar')
            <a href="{{ route('academico.horarios.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent hover:underline">
                Ir a Horarios →
            </a>
        @endcan
    </div>
</div>
