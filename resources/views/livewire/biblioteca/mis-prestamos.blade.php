<?php

use App\Modules\Biblioteca\Services\BibliotecaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.ver_propio'), 403);
    }

    public function with(BibliotecaService $service): array
    {
        return [
            'prestamos' => $service->misPrestamos(Auth::user()),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis préstamos</h1>
        <p class="mt-1 text-sm text-ink-dim">Historial de libros que has pedido prestados.</p>
    </x-slot>

    <div class="space-y-2">
        @forelse ($prestamos as $prestamo)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-surface p-3">
                <div>
                    <p class="text-sm font-semibold text-ink">{{ $prestamo->ejemplar->libro->titulo }}</p>
                    <p class="text-xs text-ink-faint">
                        {{ $prestamo->ejemplar->libro->autor }} · prestado {{ $prestamo->fecha_prestamo->format('d/m/Y') }}
                        @if ($prestamo->fecha_devolucion_real)
                            · devuelto {{ $prestamo->fecha_devolucion_real->format('d/m/Y') }}
                        @else
                            · vence {{ $prestamo->fecha_devolucion_esperada->format('d/m/Y') }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($prestamo->estaVencido())
                        <x-badge variant="danger">Vencido</x-badge>
                    @endif
                    <x-badge variant="{{ $prestamo->estado->variantePildora() }}">{{ $prestamo->estado->label() }}</x-badge>
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">Todavía no has pedido ningún libro prestado.</p>
        @endforelse
    </div>
</div>
