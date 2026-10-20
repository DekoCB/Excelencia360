@props(['contratos', 'puedeGestionar', 'contratoId', 'mostrarFormContrato', 'tipo', 'fechaInicio', 'fechaFin', 'monto', 'observaciones'])

{{--
    Contratos de este usuario (punto 10 del backlog): ya no es exclusivo de
    Docente, así que vive en la ficha de Usuario en vez de en una pantalla
    propia. Solo se ofrece a personal institucional (ver
    User::esPersonalInstitucional()) -- Estudiante/Apoderado tienen cuenta
    pero no son personal contratado. Compartido entre la página completa
    (usuarios/show.blade.php) y el modal "Ver" (usuarios/ficha-modal.blade.php),
    igual que detalle-usuario.blade.php -- ambos exponen
    abrirFormContratoCrear()/abrirFormContratoEditar()/guardarContrato()/
    eliminarContrato() y los mismos campos de formulario.
--}}
<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink">Contratos</h2>
        @if ($puedeGestionar)
            <button type="button" wire:click="abrirFormContratoCrear" class="text-xs font-medium text-accent hover:underline">+ Nuevo contrato</button>
        @endif
    </div>

    @if ($mostrarFormContrato)
        <form wire:submit="guardarContrato" class="mt-4 space-y-3 rounded-md border border-border bg-surface-2 p-3">
            <div>
                <x-input-label for="contratoTipo" value="Tipo de contrato" />
                <x-text-input wire:model="tipo" id="contratoTipo" class="mt-1 block w-full" placeholder="Plazo fijo, locación de servicios…" />
                <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="contratoFechaInicio" value="Fecha de inicio" />
                    <x-date-input wire:model="fechaInicio" id="contratoFechaInicio" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="contratoFechaFin" value="Fecha de fin (opcional)" />
                    <x-date-input wire:model="fechaFin" id="contratoFechaFin" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="contratoMonto" value="Monto mensual (S/, opcional)" />
                <x-text-input wire:model="monto" id="contratoMonto" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('monto')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contratoObservaciones" value="Observaciones (opcional)" />
                <textarea wire:model="observaciones" id="contratoObservaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <x-input-error :messages="$errors->get('observaciones')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contratoDocumento" value="Documento firmado (PDF, opcional)" />
                <input wire:model="documento" id="contratoDocumento" type="file" accept="application/pdf" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-2 file:text-sm file:text-ink">
                <x-input-error :messages="$errors->get('documento')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" wire:click="cerrarFormContrato">Cancelar</x-secondary-button>
                <x-primary-button type="submit">{{ $contratoId === null ? 'Registrar' : 'Guardar cambios' }}</x-primary-button>
            </div>
        </form>
    @endif

    <div class="mt-4 divide-y divide-border">
        @forelse ($contratos as $contrato)
            <div class="flex items-center justify-between gap-3 py-3 text-sm" wire:key="contrato-{{ $contrato->id }}">
                <div>
                    <p class="text-ink">
                        {{ $contrato->tipo }}
                        <x-badge :variant="$contrato->estaVigente() ? 'ok' : 'neutral'" class="ml-2">
                            {{ $contrato->estaVigente() ? 'Vigente' : 'Vencido' }}
                        </x-badge>
                    </p>
                    <p class="mt-0.5 text-xs text-ink-faint">
                        {{ $contrato->fecha_inicio->format('d/m/Y') }} — {{ $contrato->fecha_fin?->format('d/m/Y') ?? 'indefinido' }}
                        @if ($contrato->monto !== null)
                            · S/ {{ number_format((float) $contrato->monto, 2) }}
                        @endif
                        @if ($contrato->getFirstMedia('documento'))
                            · <a href="{{ $contrato->getFirstMediaUrl('documento') }}" target="_blank" class="font-medium text-accent hover:underline">Ver documento</a>
                        @endif
                    </p>
                </div>
                @if ($puedeGestionar)
                    <div class="flex shrink-0 items-center gap-2 text-xs">
                        <button type="button" wire:click="abrirFormContratoEditar({{ $contrato->id }})" class="font-medium text-accent hover:underline">Editar</button>
                        <button
                            type="button"
                            x-data
                            x-on:click="$store.confirm.preguntar('¿Eliminar este contrato?', () => $wire.eliminarContrato({{ $contrato->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })"
                            class="font-medium text-danger hover:underline"
                        >Eliminar</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-sm text-ink-faint">Sin contratos registrados.</p>
        @endforelse
    </div>
</div>
