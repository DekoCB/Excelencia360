@props(['certificado'])

<x-modal name="detalle-certificado" :tv="true" max-width="lg">
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
        <div>
            <h2 class="font-display text-lg text-ink">{{ $certificado?->estudiante?->nombreCompleto() ?? 'Detalle del documento' }}</h2>
            @if ($certificado)
                <p class="text-sm text-ink-dim">{{ $certificado->tipo->label() }} · N.° {{ $certificado->numero }}</p>
            @endif
        </div>
        <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
            <x-heroicon-o-x-mark class="h-5 w-5" />
        </button>
    </div>

    <div class="max-h-[75vh] overflow-y-auto p-6" wire:loading.class="opacity-50">
        @if ($certificado)
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-faint">DNI</dt>
                    <dd class="text-ink">{{ $certificado->estudiante?->dni ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Código de verificación</dt>
                    <dd class="font-mono text-ink">{{ $certificado->codigo_verificacion }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Fecha de emisión</dt>
                    <dd class="text-ink">{{ $certificado->fecha_emision->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Emitido por</dt>
                    <dd class="text-ink">{{ $certificado->emisor?->name ?? '—' }}</dd>
                </div>

                @if ($certificado->matricula)
                    <div>
                        <dt class="text-ink-faint">Semestre</dt>
                        <dd class="text-ink">{{ $certificado->matricula->grado->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">Período de matrícula</dt>
                        <dd class="text-ink">{{ $certificado->matricula->ciclo->nombre }}</dd>
                    </div>
                @endif

                @if ($certificado->cursoCapacitacion)
                    <div class="sm:col-span-2">
                        <dt class="text-ink-faint">Curso de capacitación</dt>
                        <dd class="text-ink">{{ $certificado->cursoCapacitacion->nombre }} ({{ $certificado->cursoCapacitacion->horas_lectivas }} h)</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">Número de registro</dt>
                        <dd class="font-mono text-ink">{{ $certificado->numero_registro ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">Nota</dt>
                        <dd class="text-ink">{{ $certificado->nota ?? '—' }}</dd>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <dt class="text-ink-faint">Entrega</dt>
                    <dd class="text-ink">
                        @if ($certificado->entregado_en)
                            Entregado el {{ $certificado->entregado_en->format('d/m/Y') }} por {{ $certificado->entregadoPor?->name }}
                        @elseif ($certificado->metodo_entrega?->value === 'virtual')
                            Pendiente de envío a {{ $certificado->correo_entrega }}
                        @else
                            Pendiente de recojo
                        @endif
                    </dd>
                </div>

                @if ($certificado->observaciones)
                    <div class="sm:col-span-2">
                        <dt class="text-ink-faint">Observaciones</dt>
                        <dd class="text-ink">{{ $certificado->observaciones }}</dd>
                    </div>
                @endif

                @if ($certificado->es_duplicado)
                    <div class="sm:col-span-2">
                        <x-badge variant="warn">Este es un duplicado del certificado original</x-badge>
                    </div>
                @endif
            </dl>
        @else
            <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
        @endif
    </div>
</x-modal>
