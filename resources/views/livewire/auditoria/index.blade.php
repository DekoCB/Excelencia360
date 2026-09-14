<?php

use App\Modules\Identidad\Services\AuditService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $evento = '';

    public string $tipoModelo = '';

    public function mount(): void
    {
        Gate::authorize('auditoria.ver');
    }

    public function updatingEvento(): void
    {
        $this->resetPage();
    }

    public function updatingTipoModelo(): void
    {
        $this->resetPage();
    }

    public function with(AuditService $service): array
    {
        return [
            'entradas' => $service->listar($this->evento ?: null, $this->tipoModelo ?: null),
            'tiposModelo' => $service->tiposDeModeloAuditados(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Auditoría</h1>
        <p class="mt-1 text-sm text-ink-dim">Historial de cambios sobre los registros del sistema.</p>
    </x-slot>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <x-select-input
            wire:model.live="evento"
            class="w-full sm:max-w-xs"
            :options="[
                '' => 'Todos los eventos',
                'created' => 'Creaciones',
                'updated' => 'Actualizaciones',
                'deleted' => 'Eliminaciones',
            ]"
        />

        <x-select-input
            wire:model.live="tipoModelo"
            class="w-full sm:max-w-xs"
            :options="collect($tiposModelo)->mapWithKeys(fn ($tipo) => [$tipo => class_basename($tipo)])->prepend('Todos los modelos', '')"
        />
    </div>

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Cuándo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Quién</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Evento</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Registro</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($entradas as $entrada)
                    <tr wire:key="log-{{ $entrada->id }}">
                        <td class="px-4 py-3 text-ink-dim" title="{{ $entrada->created_at?->format('d/m/Y H:i') }}">{{ $entrada->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-ink">{{ $entrada->user?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $entrada->event === 'created',
                                'bg-info/10 text-info' => $entrada->event === 'updated',
                                'bg-danger/10 text-danger' => $entrada->event === 'deleted',
                            ])>
                                {{ match ($entrada->event) {
                                    'created' => 'Creado',
                                    'updated' => 'Actualizado',
                                    'deleted' => 'Eliminado',
                                    default => $entrada->event,
                                } }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ class_basename($entrada->auditable_type) }} #{{ $entrada->auditable_id }}</td>
                        <td class="px-4 py-3 font-mono text-ink-faint">{{ $entrada->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay eventos de auditoría con estos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entradas->links() }}
    </div>
</div>
