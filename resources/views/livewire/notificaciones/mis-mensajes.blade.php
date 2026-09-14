<?php

use App\Modules\Notificaciones\Services\MensajeWhatsappService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('notificaciones.ver_propio') && $user->estudiante, 403);
    }

    public function with(MensajeWhatsappService $service): array
    {
        return [
            'mensajes' => $service->misMensajes(Auth::user()->estudiante),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis mensajes</h1>
        <p class="mt-1 text-sm text-ink-dim">Avisos y recordatorios enviados por el colegio.</p>
    </x-slot>

    <div class="space-y-3">
        @forelse ($mensajes as $mensaje)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4" wire:key="mensaje-{{ $mensaje->id }}">
                <div class="flex items-center justify-between gap-2">
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-info/10 text-info' => $mensaje->tipo->value === 'campania',
                        'bg-warn/10 text-warn' => $mensaje->tipo->value === 'recordatorio',
                    ])>{{ $mensaje->tipo->label() }}</span>
                    <span class="text-xs text-ink-faint">{{ ($mensaje->enviado_en ?? $mensaje->created_at)?->format('d/m/Y H:i') }}</span>
                </div>

                <p class="mt-2 text-sm text-ink-dim">{{ $mensaje->contenido }}</p>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
                Todavía no tienes mensajes.
            </p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $mensajes->links() }}
    </div>
</div>
