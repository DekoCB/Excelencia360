@props(['historial', 'puedeExportar' => false])

<div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
    <div class="flex items-center justify-between gap-4">
        <h2 class="text-sm font-semibold text-ink">Documentos</h2>
        @if ($puedeExportar)
            <x-secondary-button type="button" wire:click="exportarDocumentosPdf">Exportar PDF</x-secondary-button>
        @endif
    </div>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Subidos al matricularse</h3>
    <div class="mt-2 divide-y divide-border">
        @forelse ($historial['documentosSubidos'] as $documento)
            <div class="flex items-center justify-between py-2 text-sm">
                <span class="text-ink">{{ $documento->tipo->label() }}</span>
                <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $documento->verificado, 'bg-warn/10 text-warn' => ! $documento->verificado])>
                    {{ $documento->verificado ? 'Verificado' : 'Pendiente' }}
                </span>
            </div>
        @empty
            <p class="py-2 text-sm text-ink-faint">No se han subido documentos.</p>
        @endforelse
    </div>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Emitidos por la institución</h3>
    <div class="mt-2 divide-y divide-border">
        @forelse ($historial['documentosEmitidos'] as $certificado)
            <div class="flex items-center justify-between py-2 text-sm">
                <span class="text-ink">{{ $certificado->tipo->label() }} · N.° {{ $certificado->numero }}</span>
                <span class="text-ink-faint">{{ $certificado->fecha_emision->format('d/m/Y') }} · {{ $certificado->entregado_en ? 'Entregado' : 'Sin entregar' }}</span>
            </div>
        @empty
            <p class="py-2 text-sm text-ink-faint">No se han emitido certificados ni constancias.</p>
        @endforelse
    </div>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Libretas generadas</h3>
    <div class="mt-2 divide-y divide-border">
        @forelse ($historial['libretas'] as $libreta)
            <div class="flex items-center justify-between py-2 text-sm">
                <span class="text-ink">{{ $libreta->ciclo->nombre }}</span>
                <span class="text-ink-faint">{{ $libreta->generado_en?->format('d/m/Y') }} · {{ $libreta->entregado_en ? 'Entregada' : 'Sin entregar' }}</span>
            </div>
        @empty
            <p class="py-2 text-sm text-ink-faint">No se han generado libretas.</p>
        @endforelse
    </div>
</div>
