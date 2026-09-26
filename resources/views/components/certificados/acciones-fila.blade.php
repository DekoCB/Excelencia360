@props([
    'certificado',
    'puedeEmitir' => false,
    'puedeDuplicar' => false,
    'tipoEntrega' => null,
])

@php
    $confirmacionDuplicado = $certificado->tipo->esConstancia() ? 'esta constancia' : 'este certificado';
    $argumentoEntrega = $tipoEntrega !== null ? "'{$tipoEntrega}', {$certificado->id}" : (string) $certificado->id;
@endphp

<div class="flex items-center gap-1">
    <button type="button" wire:click="verDetalle({{ $certificado->id }})" x-on:click="$dispatch('open-modal', 'detalle-certificado')" title="Ver detalle" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink">
        <x-heroicon-o-eye class="h-4 w-4" />
    </button>

    @if ($certificado->getFirstMedia('pdf'))
        <a href="{{ $certificado->getFirstMediaUrl('pdf') }}" target="_blank" title="Ver PDF" class="rounded-md p-1.5 text-accent transition hover:bg-surface-2">
            <x-heroicon-o-document-text class="h-4 w-4" />
        </a>
    @endif

    @if ($puedeEmitir && ! $certificado->entregado_en)
        <button type="button" wire:click="iniciarEntrega({{ $argumentoEntrega }})" title="Marcar entregado" class="rounded-md p-1.5 text-ok transition hover:bg-surface-2">
            <x-heroicon-o-check class="h-4 w-4" />
        </button>
    @endif

    @if ($puedeDuplicar)
        <button type="button" x-on:click="$store.confirm.preguntar('¿Emitir un duplicado de {{ $confirmacionDuplicado }}?', () => $wire.duplicar({{ $certificado->id }}), { etiquetaConfirmar: 'Duplicar' })" title="Duplicar" class="rounded-md p-1.5 text-ink-dim transition hover:bg-surface-2 hover:text-ink">
            <x-heroicon-o-square-2-stack class="h-4 w-4" />
        </button>
    @endif

    @if ($puedeEmitir)
        <button type="button" wire:click="iniciarEdicionCertificado({{ $certificado->id }})" x-on:click="$dispatch('open-modal', 'editar-certificado')" title="Editar" class="rounded-md p-1.5 text-ink-dim transition hover:bg-surface-2 hover:text-ink">
            <x-heroicon-o-pencil class="h-4 w-4" />
        </button>
    @endif
</div>
