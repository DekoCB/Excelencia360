<?php

use App\Modules\Identidad\Services\SessionControlService;
use Livewire\Volt\Component;

new class extends Component
{
    public function revocar(string $sesionId, SessionControlService $service): void
    {
        $service->revocar($sesionId);
    }

    public function revocarTodas(SessionControlService $service): void
    {
        $service->revocarTodasMenosActual(auth()->user(), session()->getId());

        session()->flash('status', 'sessions-revoked');
    }

    public function with(SessionControlService $service): array
    {
        return [
            'sesiones' => $service->sesionesDe(auth()->user(), session()->getId()),
            'horasPorNombre' => $service->horasPorNombre(auth()->user()),
        ];
    }
}; ?>

<section>
    <header class="flex items-start justify-between">
        <div>
            <h2 class="text-sm font-semibold text-ink">Sesiones activas</h2>
            <p class="mt-1 text-sm text-ink-dim">
                Los dispositivos donde tu cuenta ha iniciado sesión actualmente.
            </p>
        </div>
        <button x-on:click="$store.confirm.preguntar('¿Cerrar todas las demás sesiones?', () => $wire.revocarTodas(), { peligro: true, etiquetaConfirmar: 'Cerrar sesiones' })" class="text-sm font-medium text-danger hover:underline">
            Cerrar las demás
        </button>
    </header>

    @if (session('status') === 'sessions-revoked')
        <p class="mt-2 text-sm text-ok">Se cerraron las demás sesiones.</p>
    @endif

    <div class="mt-4 divide-y divide-border">
        @foreach ($sesiones as $sesion)
            <div class="flex items-center justify-between py-3 text-sm" wire:key="sesion-{{ $sesion->id }}">
                <div>
                    <p class="text-ink">
                        {{ $sesion->nombre ?? 'Sin nombre registrado' }}
                        @if ($sesion->esActual)
                            <x-badge variant="accent" class="ml-2">Este dispositivo</x-badge>
                        @endif
                    </p>
                    <p class="text-xs text-ink-faint">
                        {{ $sesion->ipAddress ?? 'IP desconocida' }} · {{ \Illuminate\Support\Str::limit($sesion->userAgent ?? 'Agente desconocido', 60) }}
                        · última actividad {{ \Illuminate\Support\Carbon::createFromTimestamp($sesion->lastActivity)->diffForHumans() }}
                    </p>
                </div>
                @unless ($sesion->esActual)
                    <button x-on:click="$store.confirm.preguntar('¿Cerrar esta sesión?', () => $wire.revocar(@js($sesion->id)), { peligro: true, etiquetaConfirmar: 'Cerrar sesión' })" class="text-sm font-medium text-danger hover:underline">
                        Cerrar
                    </button>
                @endunless
            </div>
        @endforeach
    </div>

    @if ($horasPorNombre->isNotEmpty())
        <div class="mt-6 border-t border-border pt-4">
            <h3 class="text-sm font-semibold text-ink">Horas registradas por nombre</h3>
            <p class="mt-1 text-xs text-ink-faint">
                Suma de los ingresos ya cerrados de esta cuenta, agrupados por el nombre escrito en cada login.
            </p>

            <div class="mt-3 divide-y divide-border">
                @foreach ($horasPorNombre as $fila)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span class="text-ink">{{ $fila['nombre'] }}</span>
                        <span class="text-ink-dim">{{ number_format($fila['horas'], 1) }} h · {{ $fila['ingresos'] }} {{ \Illuminate\Support\Str::plural('ingreso', $fila['ingresos']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
