<?php

use App\Models\User;
use App\Modules\Vacaciones\Services\VacacionService;
use App\Shared\Enums\RolEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public string $terminoBusqueda = '';

    public ?int $userId = null;

    public string $userNombre = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public function mount(): void
    {
        Gate::authorize('vacaciones.ver');
    }

    public function abrirModal(): void
    {
        Gate::authorize('vacaciones.gestionar');

        $this->resetValidation();
        $this->reset(['terminoBusqueda', 'userId', 'userNombre', 'fechaInicio', 'fechaFin']);
        $this->mostrarModal = true;
    }

    public function seleccionarUsuario(int $userId, string $nombre): void
    {
        $this->userId = $userId;
        $this->userNombre = $nombre;
        $this->terminoBusqueda = '';
    }

    public function activar(VacacionService $service): void
    {
        Gate::authorize('vacaciones.gestionar');

        $this->validate([
            'userId' => 'required|integer|exists:users,id',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ]);

        $user = User::query()->findOrFail($this->userId);
        $service->activar($user, $this->fechaInicio, $this->fechaFin, Auth::id());

        $this->mostrarModal = false;
        session()->flash('status', 'Vacaciones registradas correctamente.');
    }

    public function with(VacacionService $service): array
    {
        $resultadosBusqueda = collect();

        if ($this->terminoBusqueda !== '') {
            $termino = $this->terminoBusqueda;
            $resultadosBusqueda = User::query()
                ->where('name', 'like', "%{$termino}%")
                ->whereHas('roles', fn ($query) => $query->whereNotIn('name', [RolEnum::ESTUDIANTE->value, RolEnum::APODERADO->value]))
                ->limit(8)
                ->get();
        }

        return [
            'resultadosBusqueda' => $resultadosBusqueda,
            'vigentes' => $service->vigentes(),
            'historial' => $service->historial(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Vacaciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Periodos de descanso del personal (docentes, administrativos, etc.), con fechas libres. Es solo informativo: no afecta asistencia ni aula virtual.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @can('vacaciones.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Registrar vacaciones
            </x-primary-button>
        </div>
    @endcan

    <div class="space-y-6">
        <div class="rounded-2xl border border-border bg-surface shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <h2 class="font-display text-sm text-ink">En vacaciones ahora</h2>
            </div>
            <div class="divide-y divide-border">
                @forelse ($vigentes as $vacacion)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <p class="text-ink">{{ $vacacion->user->name }}</p>
                        <p class="text-ink-dim">{{ $vacacion->fecha_inicio->format('d/m/Y') }} – {{ $vacacion->fecha_fin->format('d/m/Y') }}</p>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Nadie está de vacaciones hoy.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <h2 class="font-display text-sm text-ink">Historial</h2>
            </div>
            <div class="divide-y divide-border">
                @forelse ($historial as $vacacion)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <p class="text-ink">{{ $vacacion->user->name }}</p>
                        <p class="text-ink-dim">{{ $vacacion->fecha_inicio->format('d/m/Y') }} – {{ $vacacion->fecha_fin->format('d/m/Y') }}</p>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay vacaciones registradas.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">Registrar vacaciones</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label value="Personal" />
                    @if ($userId)
                        <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                            {{ $userNombre }}
                            <button type="button" wire:click="$set('userId', null)" class="text-xs underline">Cambiar</button>
                        </div>
                    @else
                        <x-text-input wire:model.live.debounce.300ms="terminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre…" />
                        @if ($resultadosBusqueda->isNotEmpty())
                            <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                                @foreach ($resultadosBusqueda as $usuario)
                                    <button
                                        type="button"
                                        wire:click="seleccionarUsuario({{ $usuario->id }}, '{{ addslashes($usuario->name) }}')"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                    >
                                        {{ $usuario->name }}
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($terminoBusqueda !== '')
                            <p class="mt-1 text-xs text-ink-faint">Sin resultados.</p>
                        @endif
                    @endif
                    <x-input-error :messages="$errors->get('userId')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="fechaInicio" value="Fecha de inicio" />
                        <x-date-input wire:model="fechaInicio" id="fechaInicio" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fechaFin" value="Fecha de fin" />
                        <x-date-input wire:model="fechaFin" id="fechaFin" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="activar">Registrar</x-primary-button>
            </div>
        </div>
    </div>
</div>
