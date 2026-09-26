@props(['certificado'])

<x-modal name="editar-certificado" max-width="md">
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
        <div>
            <h2 class="font-display text-lg text-ink">Editar documento</h2>
            @if ($certificado)
                <p class="text-sm text-ink-dim">{{ $certificado->estudiante?->nombreCompleto() ?? '—' }} · N.° {{ $certificado->numero }}</p>
            @endif
        </div>
        <button type="button" x-on:click="$dispatch('close'); $wire.cancelarEdicionCertificado()" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
            <x-heroicon-o-x-mark class="h-5 w-5" />
        </button>
    </div>

    <form wire:submit="guardarEdicionCertificado" class="space-y-4 p-6">
        @if ($certificado?->cursoCapacitacion || $certificado?->tipo?->esCapacitacion())
            <div>
                <x-input-label for="editNumeroRegistro" value="Número de registro" />
                <x-text-input wire:model="editNumeroRegistro" id="editNumeroRegistro" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('editNumeroRegistro')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="editNota" value="Nota (opcional)" />
                <x-text-input wire:model="editNota" id="editNota" type="number" min="0" max="20" step="0.01" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('editNota')" class="mt-1" />
            </div>
        @endif

        <div>
            <x-input-label for="editObservaciones" value="Observaciones" />
            <textarea wire:model="editObservaciones" id="editObservaciones" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
            <x-input-error :messages="$errors->get('editObservaciones')" class="mt-1" />
        </div>

        <p class="text-xs text-ink-faint">Al guardar se vuelve a generar el PDF del documento con estos datos.</p>

        <div class="flex justify-end gap-3">
            <x-secondary-button type="button" x-on:click="$dispatch('close'); $wire.cancelarEdicionCertificado()">Cancelar</x-secondary-button>
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="guardarEdicionCertificado">
                <span wire:loading.remove wire:target="guardarEdicionCertificado">Guardar y regenerar PDF</span>
                <span wire:loading wire:target="guardarEdicionCertificado">Guardando…</span>
            </x-primary-button>
        </div>
    </form>
</x-modal>
