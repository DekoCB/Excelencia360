@props([
    'estudiante',
    'documentos',
    'examenes',
    'matriculas',
    'cursosConHorarios' => [],
    'editandoHorarioMatriculaId' => null,
    'editandoHorarioCursoId' => null,
    'horarioSeleccionado' => '',
    'editandoFechaFinMatriculaId' => null,
    'fechaFinEstudioNueva' => '',
    'planesPorMatricula' => [],
    'editandoMontoPlanId' => null,
    'montoTotalNuevo' => '',
    'cargosAdicionales' => [],
    'editandoMontoCargoId' => null,
    'montoCargoNuevo' => '',
    'agregandoCargo' => false,
    'cargoConceptoNuevo' => '',
    'cargoMontoNuevo' => '',
])

{{--
    Contenido de la ficha del estudiante, compartido entre la página
    completa (matricula/show.blade.php, para acceso directo por URL) y el
    modal "Ver" de matricula/index.blade.php. El wire:click de "Verificar"
    documento llama al componente Livewire que envuelve este parcial, así
    que ambos (show y index) deben exponer verificarDocumento().
--}}
<div class="space-y-6">
    <div class="flex justify-center">
        @if ($estudiante->fotoUrl())
            <img src="{{ $estudiante->fotoUrl() }}" alt="" class="h-24 w-24 shrink-0 rounded-full object-cover">
        @else
            <span class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border border-dashed border-border bg-surface-2 text-ink-faint">
                <x-heroicon-o-user class="h-10 w-10" />
            </span>
        @endif
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Datos personales</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-ink-faint">Fecha de nacimiento</dt><dd class="text-ink">{{ $estudiante->fecha_nacimiento->format('d/m/Y') }}</dd></div>
            <div><dt class="text-ink-faint">Estado civil</dt><dd class="text-ink">{{ $estudiante->estado_civil?->label() ?? '—' }}</dd></div>
            <div>
                <dt class="text-ink-faint">Celular</dt>
                <dd class="text-ink">
                    {{ $estudiante->celular ?? '—' }}
                    @foreach ($estudiante->telefonos as $telefono)
                        <span class="block text-ink-dim">{{ $telefono->numero }}</span>
                    @endforeach
                </dd>
            </div>
            <div><dt class="text-ink-faint">Correo</dt><dd class="text-ink">{{ $estudiante->email ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-ink-faint">Dirección</dt><dd class="text-ink">{{ $estudiante->direccion ?? '—' }}</dd></div>
            <div><dt class="text-ink-faint">Semestre actual</dt><dd class="text-ink">{{ $estudiante->gradoActual?->nombre ?? '—' }}</dd></div>
            <div>
                <dt class="text-ink-faint">Ciclos completados</dt>
                <dd class="text-ink">{{ $estudiante->ciclos_completados }}{{ $matriculas->last()?->ciclo?->modalidad?->value !== 'anual' ? ' / 4' : '' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Observaciones</h2>
        <p class="mt-1 text-xs text-ink-faint">Acuerdos especiales, documentos pendientes de entregar, casos particulares (ej. examen de ubicación).</p>
        @can('matricula.editar')
            <form wire:submit="guardarObservaciones" class="mt-4">
                <textarea wire:model="observacionesTexto" rows="3" class="block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent" placeholder="Sin observaciones registradas."></textarea>
                <div class="mt-2 flex justify-end">
                    <x-secondary-button type="submit">Guardar observaciones</x-secondary-button>
                </div>
            </form>
        @else
            <p class="mt-4 whitespace-pre-line text-sm text-ink">{{ $estudiante->observaciones ?: 'Sin observaciones registradas.' }}</p>
        @endcan
    </div>

    @if ($estudiante->es_menor_edad && $estudiante->apoderado)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Apoderado</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-faint">Nombres</dt><dd class="text-ink">{{ $estudiante->apoderado->nombres }}</dd></div>
                <div><dt class="text-ink-faint">DNI</dt><dd class="text-ink">{{ $estudiante->apoderado->dni }}</dd></div>
                <div><dt class="text-ink-faint">Parentesco</dt><dd class="text-ink">{{ $estudiante->apoderado->parentesco }}</dd></div>
                <div><dt class="text-ink-faint">Celular</dt><dd class="text-ink">{{ $estudiante->apoderado->celular }}</dd></div>
            </dl>
        </div>
    @endif

    @if ($estudiante->institucionProcedencia)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Institución de procedencia</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-ink-faint">Colegio</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->nombre_colegio }}</dd></div>
                <div><dt class="text-ink-faint">Ubicación</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->ubicacion ?? '—' }}</dd></div>
                <div><dt class="text-ink-faint">Año de egreso</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->anio_egreso ?? '—' }}</dd></div>
            </dl>
        </div>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Documentos</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($documentos as $documento)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $documento->tipo->label() }}</p>
                        @if ($documento->getFirstMedia('archivo'))
                            <a href="{{ $documento->getFirstMediaUrl('archivo') }}" target="_blank" class="text-xs text-accent hover:underline">Ver archivo</a>
                        @endif
                        @if ($documento->getFirstMedia('archivo') && $documento->getFirstMedia('reverso'))
                            <button wire:click="descargarDniPdf({{ $documento->id }})" class="ml-2 text-xs text-accent hover:underline">Descargar PDF (cara y sello)</button>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $documento->verificado, 'bg-warn/10 text-warn' => ! $documento->verificado])>
                            {{ $documento->verificado ? 'Verificado' : 'Pendiente' }}
                        </span>
                        @can('matricula.editar')
                            @unless ($documento->verificado)
                                <button wire:click="verificarDocumento({{ $documento->id }})" class="text-xs font-medium text-accent hover:underline">Verificar</button>
                            @endunless
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">No se han subido documentos.</p>
            @endforelse
        </div>
    </div>

    @if ($examenes->isNotEmpty())
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Exámenes de ubicación</h2>
            <div class="mt-4 divide-y divide-border">
                @foreach ($examenes as $examen)
                    <div class="py-3 text-sm">
                        <p class="text-ink">{{ $examen->fecha->format('d/m/Y') }} · S/ {{ number_format((float) $examen->costo, 2) }}</p>
                        <p class="text-ink-faint">Resultado: {{ $examen->resultado ?? '—' }} @if($examen->gradoAsignado) · Semestre asignado: {{ $examen->gradoAsignado->nombre }} @endif</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Matrículas</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($matriculas as $matricula)
                <div class="py-3 text-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-ink">{{ $matricula->ciclo->nombre }} · {{ $matricula->ciclo->modalidad->label() }} · {{ $matricula->grado->nombre }}</p>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-ok/10 text-ok' => $matricula->estado->value === 'aprobada',
                            'bg-warn/10 text-warn' => $matricula->estado->value === 'pendiente' || $matricula->estado->value === 'observada',
                            'bg-danger/10 text-danger' => $matricula->estado->value === 'anulada',
                        ])>
                            {{ $matricula->estado->label() }}
                        </span>
                    </div>
                    <p class="mt-1 text-ink-faint">Matriculado el {{ $matricula->fecha_matricula->format('d/m/Y') }}</p>
                    <p class="mt-1 text-ink-faint">Aula: {{ $matricula->grado->letraAula() }}</p>

                    <div class="mt-2 flex items-center gap-2">
                        <p class="text-ink-faint">Fin de estudios: {{ $matricula->fecha_fin_estudio?->format('d/m/Y') ?? '—' }}</p>

                        @can('matricula.editar')
                            @if ($editandoFechaFinMatriculaId !== $matricula->id)
                                <button type="button" wire:click="editarFechaFinEstudio({{ $matricula->id }})" class="text-xs font-medium text-accent hover:underline">Editar</button>
                            @endif
                        @endcan
                    </div>

                    @can('matricula.editar')
                        @if ($editandoFechaFinMatriculaId === $matricula->id)
                            <form wire:submit="guardarFechaFinEstudio" class="mt-2 flex flex-wrap items-center gap-2">
                                <x-date-input wire:model="fechaFinEstudioNueva" class="w-40 text-xs" />
                                <x-secondary-button type="submit">Guardar</x-secondary-button>
                                <button type="button" wire:click="cancelarEdicionFechaFinEstudio" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                            </form>
                            <x-input-error :messages="$errors->get('fechaFinEstudioNueva')" class="mt-1" />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                        @endif
                    @endcan

                    <div class="mt-2">
                        <p class="text-ink-faint">Horarios:</p>
                        <div class="mt-1 space-y-1">
                            @forelse ($cursosConHorarios[$matricula->id] ?? [] as $entrada)
                                @php $editandoEsteCurso = $editandoHorarioMatriculaId === $matricula->id && $editandoHorarioCursoId === $entrada['curso']->id; @endphp
                                <div class="flex items-center justify-between gap-2">
                                    <p>
                                        <span class="text-ink">{{ $entrada['curso']->nombre }}:</span>
                                        @if ($entrada['asignado'])
                                            <span class="text-ink-faint">{{ $entrada['asignado']->docente?->name }} · {{ $entrada['asignado']->diasResumen() }}</span>
                                        @elseif ($entrada['ambiguo'])
                                            <span class="font-medium text-warn">Sin asignar — elige sección</span>
                                        @elseif ($entrada['opciones']->isNotEmpty())
                                            <span class="text-ink-faint">{{ $entrada['opciones']->first()->docente?->name }} · {{ $entrada['opciones']->first()->diasResumen() }} (automático)</span>
                                        @else
                                            <span class="text-ink-faint">Sin horario creado en este ciclo todavía.</span>
                                        @endif
                                    </p>

                                    @can('matricula.editar')
                                        @if ($entrada['opciones']->isNotEmpty() && ! $editandoEsteCurso)
                                            <button type="button" wire:click="editarHorario({{ $matricula->id }}, {{ $entrada['curso']->id }})" class="shrink-0 text-xs font-medium text-accent hover:underline">Editar</button>
                                        @endif
                                    @endcan
                                </div>

                                @can('matricula.editar')
                                    @if ($editandoEsteCurso)
                                        <form wire:submit="guardarHorario" class="flex flex-wrap items-center gap-2">
                                            <x-select-input
                                                wire:model="horarioSeleccionado"
                                                class="w-72 text-xs"
                                                placeholder="Sin horario asignado"
                                                :options="$entrada['opciones']->mapWithKeys(fn ($horario) => [$horario->id => ($horario->docente?->name ?? 'Sin docente').' · '.$horario->diasResumen()])"
                                            />
                                            <x-secondary-button type="submit">Guardar</x-secondary-button>
                                            <button type="button" wire:click="cancelarEdicionHorario" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                                        </form>
                                        <x-input-error :messages="$errors->get('horario')" class="mt-1" />
                                    @endif
                                @endcan
                            @empty
                                <p class="text-xs text-ink-faint">Este semestre no tiene cursos con horario en este programa de estudio todavía.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-2 flex gap-4">
                        @if ($matricula->getFirstMedia('ficha'))
                            <a href="{{ $matricula->getFirstMediaUrl('ficha') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ficha de matrícula (PDF)</a>
                        @else
                            <span class="text-xs text-ink-faint">Generando ficha…</span>
                        @endif
                        @if ($matricula->getFirstMedia('constancia'))
                            <a href="{{ $matricula->getFirstMediaUrl('constancia') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Constancia de vacante (PDF)</a>
                        @else
                            <span class="text-xs text-ink-faint">Generando constancia…</span>
                        @endif
                    </div>

                    @can('pagos.ver')
                        @php $plan = $planesPorMatricula[$matricula->id] ?? null; @endphp
                        <div class="mt-3 border-t border-border pt-3">
                            @if (! $plan)
                                <p class="text-xs text-ink-faint">Sin plan de pago asignado para este ciclo.</p>
                            @else
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-medium text-ink-dim">
                                        Plan de pago · {{ $plan->numero_cuotas }} {{ $plan->numero_cuotas === 1 ? 'cuota' : 'cuotas' }} · S/ {{ number_format((float) $plan->monto_total, 2) }}
                                    </p>
                                    @can('pagos.gestionar')
                                        @if ($editandoMontoPlanId !== $plan->id)
                                            <button type="button" wire:click="editarMontoPlan({{ $plan->id }})" class="shrink-0 text-xs font-medium text-accent hover:underline">Editar monto</button>
                                        @endif
                                    @endcan
                                </div>

                                @can('pagos.gestionar')
                                    @if ($editandoMontoPlanId === $plan->id)
                                        <form wire:submit="guardarMontoPlan" class="mt-2 flex flex-wrap items-center gap-2">
                                            <span class="text-xs text-ink-faint">Monto total (S/)</span>
                                            <input type="number" step="0.01" min="0.01" wire:model="montoTotalNuevo" class="w-28 rounded-md border-border bg-surface text-xs text-ink focus:border-accent focus:ring-accent">
                                            <x-secondary-button type="submit">Guardar</x-secondary-button>
                                            <button type="button" wire:click="cancelarEdicionMontoPlan" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                                        </form>
                                        <p class="mt-1 text-xs text-ink-faint">La diferencia se reparte entre las cuotas pendientes; las ya pagadas o exoneradas no cambian.</p>
                                        <x-input-error :messages="$errors->get('montoTotalNuevo')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('montoTotal')" class="mt-1" />
                                    @endif
                                @endcan

                                <div class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                                    @foreach ($plan->cuotas as $cuota)
                                        <div class="flex items-center justify-between gap-2 rounded-md bg-surface-2 px-2 py-1 text-xs">
                                            <span class="text-ink-dim">Cuota {{ $cuota->numero }} · S/ {{ number_format((float) $cuota->monto, 2) }} · vence {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                                            <span @class([
                                                'shrink-0 rounded-full px-1.5 py-0.5 text-xs',
                                                'bg-ok/10 text-ok' => $cuota->estado->value === 'pagado',
                                                'bg-warn/10 text-warn' => $cuota->estado->value === 'pendiente' && ! $cuota->estaVencida(),
                                                'bg-danger/10 text-danger' => $cuota->estaVencida(),
                                                'bg-surface text-ink-faint' => $cuota->estado->value === 'exonerado',
                                            ])>{{ $cuota->estaVencida() ? 'Vencida' : $cuota->estado->label() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endcan
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Este estudiante todavía no tiene matrículas registradas.</p>
            @endforelse
        </div>
    </div>

    @can('pagos.ver')
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Cargos adicionales</h2>
            <p class="mt-1 text-xs text-ink-faint">Otros cobros puntuales del estudiante -- Convalidación, Exoneración, Recuperación, Visación, etc.</p>

            <div class="mt-4 divide-y divide-border">
                @forelse ($cargosAdicionales as $cargo)
                    <div class="py-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-ink">{{ $cargo->concepto }}</p>
                            <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $cargo->estado->value === 'pagado',
                                'bg-warn/10 text-warn' => $cargo->estado->value === 'pendiente',
                                'bg-surface-2 text-ink-faint' => $cargo->estado->value === 'exonerado',
                            ])>{{ $cargo->estado->label() }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <p class="text-ink-faint">
                                @if ($cargo->montoPagado() > 0 && $cargo->saldoPendiente() > 0)
                                    Monto: S/ {{ number_format((float) $cargo->monto, 2) }} · pagado S/ {{ number_format($cargo->montoPagado(), 2) }} · saldo S/ {{ number_format($cargo->saldoPendiente(), 2) }}
                                @else
                                    Monto: S/ {{ number_format((float) $cargo->monto, 2) }}
                                @endif
                            </p>

                            @can('pagos.gestionar')
                                @if ($editandoMontoCargoId !== $cargo->id)
                                    <button type="button" wire:click="editarMontoCargo({{ $cargo->id }})" class="shrink-0 text-xs font-medium text-accent hover:underline">Editar monto</button>
                                @endif
                            @endcan
                        </div>

                        @can('pagos.gestionar')
                            @if ($editandoMontoCargoId === $cargo->id)
                                <form wire:submit="guardarMontoCargo" class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-ink-faint">Monto (S/)</span>
                                    <input type="number" step="0.01" min="0.01" wire:model="montoCargoNuevo" class="w-28 rounded-md border-border bg-surface text-xs text-ink focus:border-accent focus:ring-accent">
                                    <x-secondary-button type="submit">Guardar</x-secondary-button>
                                    <button type="button" wire:click="cancelarEdicionMontoCargo" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                                </form>
                                <x-input-error :messages="$errors->get('montoCargoNuevo')" class="mt-1" />
                            @endif
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">Sin cargos adicionales registrados.</p>
                @endforelse
            </div>

            @can('pagos.gestionar')
                <div class="mt-4 border-t border-border pt-4">
                    @if (! $agregandoCargo)
                        <button type="button" wire:click="mostrarFormularioCargo" class="text-xs font-medium text-accent hover:underline">+ Agregar cargo</button>
                    @else
                        <form wire:submit="guardarNuevoCargo" class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="block text-xs text-ink-faint">Concepto</label>
                                <input type="text" wire:model="cargoConceptoNuevo" placeholder="Ej. Convalidación" class="mt-1 w-48 rounded-md border-border bg-surface text-xs text-ink focus:border-accent focus:ring-accent">
                            </div>
                            <div>
                                <label class="block text-xs text-ink-faint">Monto (S/)</label>
                                <input type="number" step="0.01" min="0.01" wire:model="cargoMontoNuevo" class="mt-1 w-24 rounded-md border-border bg-surface text-xs text-ink focus:border-accent focus:ring-accent">
                            </div>
                            <x-secondary-button type="submit">Guardar</x-secondary-button>
                            <button type="button" wire:click="cancelarNuevoCargo" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                        </form>
                        <x-input-error :messages="$errors->get('cargoConceptoNuevo')" class="mt-1" />
                        <x-input-error :messages="$errors->get('cargoMontoNuevo')" class="mt-1" />
                    @endif
                </div>
            @endcan
        </div>
    @endcan
</div>
