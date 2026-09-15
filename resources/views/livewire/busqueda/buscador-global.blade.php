<?php

use App\Modules\Busqueda\Services\BusquedaGlobalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

/**
 * Caja de búsqueda del topbar, embebida en layout.navigation. No tiene
 * ruta propia -- es un widget que enlaza a las páginas que ya existen en
 * cada módulo (ver BusquedaGlobalService).
 */
new class extends Component
{
    public string $termino = '';

    public function with(BusquedaGlobalService $service): array
    {
        return [
            'resultados' => mb_strlen(trim($this->termino)) >= 2
                ? $service->buscar(Auth::user(), $this->termino)
                : collect(),
        ];
    }
}; ?>

<div class="relative w-full max-w-xs" x-data="{ abierto: false }" @click.outside="abierto = false" @keydown.escape="abierto = false">
    <div class="relative">
        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
        <input
            type="text"
            wire:model.live.debounce.300ms="termino"
            @focus="abierto = true"
            @input="abierto = true"
            placeholder="Buscar estudiante, docente, personal…"
            aria-label="Búsqueda global"
            autocomplete="off"
            class="w-full rounded-md border-border bg-surface-2 py-1.5 pl-8 pr-3 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:bg-surface focus:ring-accent"
        >
    </div>

    @if (mb_strlen(trim($termino)) >= 2)
        <div
            x-show="abierto"
            x-transition
            x-cloak
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg"
        >
            @if ($resultados->isNotEmpty())
                <ul class="max-h-80 overflow-y-auto py-1">
                    @foreach ($resultados as $resultado)
                        <li>
                            <a
                                href="{{ $resultado->url }}"
                                wire:navigate
                                @click="abierto = false"
                                class="flex items-center gap-3 px-3 py-2 text-sm hover:bg-surface-2"
                            >
                                <x-dynamic-component :component="'heroicon-o-'.$resultado->icono" class="h-4 w-4 shrink-0 text-ink-faint" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-ink">{{ $resultado->titulo }}</span>
                                    <span class="block truncate text-xs text-ink-faint">{{ $resultado->subtitulo }}</span>
                                </span>
                                <span class="shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-ink-faint">{{ $resultado->tipo }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-3 py-4 text-center text-sm text-ink-faint">Sin resultados para "{{ $termino }}".</p>
            @endif
        </div>
    @endif
</div>
