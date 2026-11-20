@props([
    'ciclo',
    'horarios',
    'editando' => false,
    'nombre' => '',
    'anio' => '',
    'fechaInicio' => '',
    'fechaFin' => '',
    'estado' => '',
    'estadosCiclo' => [],
])

{{--
    Contenido del detalle de ciclo, compartido entre la página completa
    (academico/ciclos/show.blade.php, para acceso directo por URL) y el
    modal "Ver" de academico/ciclos/index.blade.php. El wire:submit de
    "Guardar" llama al componente Livewire que envuelve este parcial, así
    que ambos (show y el modal) deben exponer iniciarEdicion()/
    guardarEdicion()/cancelarEdicion() y las mismas propiedades de
    edición.
--}}
<div class="space-y-6">
    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink">Datos del ciclo</h2>
            @can('academico.gestionar')
                @if (! $editando)
                    <button type="button" wire:click="iniciarEdicion" class="text-xs font-medium text-accent hover:underline">Editar</button>
                @endif
            @endcan
        </div>

        @if (! $editando)
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-faint">Nombre</dt><dd class="text-ink">{{ $ciclo->nombre }}</dd></div>
                <div><dt class="text-ink-faint">Fechas del ciclo</dt><dd class="text-ink">{{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}</dd></div>
                <div><dt class="text-ink-faint">Estado</dt><dd class="text-ink">{{ $ciclo->estado->label() }}</dd></div>
            </dl>
        @else
            <form wire:submit="guardarEdicion" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="anio" value="Año" />
                        <x-text-input wire:model="anio" id="anio" type="number" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('anio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <x-select-input
                            wire:model="estado"
                            id="estado"
                            class="mt-1 block w-full"
                            :options="collect($estadosCiclo)->mapWithKeys(fn ($estadoOpcion) => [$estadoOpcion->value => $estadoOpcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('estado')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fechaInicio" value="Fecha de inicio del ciclo" />
                        <x-date-input wire:model="fechaInicio" id="fechaInicio" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fechaFin" value="Fecha de fin del ciclo" />
                        <x-date-input wire:model="fechaFin" id="fechaFin" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fecha_fin')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" wire:click="cancelarEdicion">Cancelar</x-secondary-button>
                    <x-primary-button type="submit">Guardar</x-primary-button>
                </div>
            </form>
        @endif
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
