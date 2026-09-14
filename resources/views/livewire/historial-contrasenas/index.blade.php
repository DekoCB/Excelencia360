<?php

use App\Modules\Identidad\Services\HistorialContrasenaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $categoria = 'personal';

    public function mount(): void
    {
        Gate::authorize('auditoria.ver');
    }

    public function updatingCategoria(): void
    {
        $this->resetPage();
    }

    public function with(HistorialContrasenaService $service): array
    {
        return [
            'entradas' => $service->listar($this->categoria === 'estudiantes'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Historial de Contraseñas</h1>
        <p class="mt-1 text-sm text-ink-dim">Cuándo cambió su contraseña cada usuario del sistema.</p>
    </x-slot>

    {{--
        Misma idea que las dos tarjetas de "¿Cómo deseas ingresar?" en el
        login: personal administrativo y estudiantes se ven por separado,
        no mezclados en una sola lista.
    --}}
    <div class="mb-4 flex gap-2">
        <button
            type="button"
            wire:click="$set('categoria', 'personal')"
            @class([
                'rounded-md px-4 py-2 font-display text-sm font-medium transition',
                'bg-accent text-white' => $categoria === 'personal',
                'border border-border bg-surface text-ink-dim hover:bg-surface-2' => $categoria !== 'personal',
            ])
        >
            Personal administrativo
        </button>
        <button
            type="button"
            wire:click="$set('categoria', 'estudiantes')"
            @class([
                'rounded-md px-4 py-2 font-display text-sm font-medium transition',
                'bg-accent text-white' => $categoria === 'estudiantes',
                'border border-border bg-surface text-ink-dim hover:bg-surface-2' => $categoria !== 'estudiantes',
            ])
        >
            Estudiantes
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Usuario</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Correo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Cuándo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($entradas as $entrada)
                    <tr wire:key="historial-contrasena-{{ $entrada->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $entrada->auditable?->name ?? 'Usuario eliminado' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $entrada->auditable?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim" title="{{ $entrada->created_at?->format('d/m/Y H:i') }}">{{ $entrada->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-faint">{{ $entrada->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-ink-faint">No hay cambios de contraseña registrados en esta categoría.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entradas->links() }}</div>
</div>
