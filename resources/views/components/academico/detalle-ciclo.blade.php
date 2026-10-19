@props([
    'ciclo',
    'horarios',
    'editando' => false,
    'nombre' => '',
    'tipo' => '',
    'modalidad' => '',
    'anio' => '',
    'fechaInicio' => '',
    'fechaFin' => '',
    'estado' => '',
    'fechaInicioMatricula' => '',
    'fechaFinMatricula' => '',
    'estadoMatricula' => '',
    'tiposCiclo' => [],
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
                <div><dt class="text-ink-faint">Modalidad</dt><dd class="text-ink">{{ $ciclo->modalidad->label() }}{{ $ciclo->tipo ? ' · '.$ciclo->tipo->label() : '' }}</dd></div>
                <div><dt class="text-ink-faint">Fechas del ciclo</dt><dd class="text-ink">{{ $ciclo->fecha_inicio->format('d/m/Y') }} – {{ $ciclo->fecha_fin->format('d/m/Y') }}</dd></div>
                <div><dt class="text-ink-faint">Estado</dt><dd class="text-ink">{{ $ciclo->estado->label() }}</dd></div>
            </dl>

            @if ($ciclo->modalidad->value === 'seis_meses')
                <div class="mt-4 border-t border-border pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Periodo de matrícula</h3>
                    @if ($ciclo->fecha_inicio_matricula && $ciclo->fecha_fin_matricula)
                        <p class="mt-2 text-sm text-ink">
                            {{ $ciclo->fecha_inicio_matricula->format('d/m/Y') }} – {{ $ciclo->fecha_fin_matricula->format('d/m/Y') }}
                            <span @class([
                                'ml-2 rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $ciclo->estado_matricula?->value === 'abierto',
                                'bg-ink-faint/10 text-ink-faint' => $ciclo->estado_matricula?->value === 'cerrado',
                            ])>
                                {{ $ciclo->estado_matricula?->label() ?? '—' }}
                            </span>
                        </p>
                    @else
                        <p class="mt-2 text-sm text-ink-faint">Todavía no se definió la ventana de matrícula de este ciclo. Usa "Editar" para fijarla.</p>
                    @endif
                </div>
            @endif
        @else
            <form wire:submit="guardarEdicion" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Modalidad" />
                        <p class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-ink-dim">{{ $ciclo->modalidad->label() }}</p>
                        <p class="mt-1 text-xs text-ink-faint">La modalidad no se puede cambiar después de creado.</p>
                    </div>
                    <div>
                        <x-input-label for="anio" value="Año" />
                        <x-text-input wire:model="anio" id="anio" type="number" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('anio')" class="mt-1" />
                    </div>
                </div>

                @if ($ciclo->modalidad->value === 'seis_meses')
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <x-select-input
                            wire:model="tipo"
                            id="tipo"
                            class="mt-1 block w-full"
                            :options="collect($tiposCiclo)->mapWithKeys(fn ($tipoOpcion) => [$tipoOpcion->value => $tipoOpcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                    </div>
                @endif

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

                @if ($ciclo->modalidad->value === 'seis_meses')
                    <div class="border-t border-border pt-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Periodo de matrícula (opcional)</h3>
                        <p class="mt-1 text-xs text-ink-faint">
                            Puede abrir hasta 30 días antes del inicio del ciclo y debe cerrar a más tardar en su fecha de fin.
                        </p>
                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="fechaInicioMatricula" value="Apertura" />
                                <x-date-input wire:model="fechaInicioMatricula" id="fechaInicioMatricula" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('fecha_inicio_matricula')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="fechaFinMatricula" value="Cierre" />
                                <x-date-input wire:model="fechaFinMatricula" id="fechaFinMatricula" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('fecha_fin_matricula')" class="mt-1" />
                            </div>
                        </div>
                        @if ($fechaInicioMatricula !== '' || $fechaFinMatricula !== '')
                            <div class="mt-3">
                                <x-input-label for="estadoMatricula" value="Estado de la matrícula" />
                                <x-select-input
                                    wire:model="estadoMatricula"
                                    id="estadoMatricula"
                                    class="mt-1 block w-full sm:w-56"
                                    :options="['abierto' => 'Abierto', 'cerrado' => 'Cerrado']"
                                />
                            </div>
                        @endif
                    </div>
                @endif

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
