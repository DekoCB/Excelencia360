<?php

use App\Modules\Identidad\Services\AuditService;
use App\Modules\Personal\Models\Personal;
use App\Modules\Personal\Services\PersonalService;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Ficha propia de Personal (punto 12 del backlog): antes solo existía el
 * modal de crear/editar sobre el listado -- esta página da una vista más
 * rica (antigüedad calculada, actividad reciente) consistente con la
 * ficha de Usuario/Docente, sin sección de Contrato porque Personal no
 * tiene cuenta de login (ver Personal::class y User::esPersonalInstitucional()).
 */
new #[Layout('layouts.app')] class extends Component
{
    public Personal $personal;

    public string $nombres = '';

    public string $apellidos = '';

    public string $celular = '';

    public string $cargo = '';

    public string $area = '';

    public string $fechaIngreso = '';

    public bool $activo = true;

    public function mount(Personal $personal): void
    {
        Gate::authorize('personal.ver');

        $this->personal = $personal;
        $this->nombres = $personal->nombres;
        $this->apellidos = $personal->apellidos;
        $this->celular = $personal->celular ?? '';
        $this->cargo = $personal->cargo;
        $this->area = $personal->area ?? '';
        $this->fechaIngreso = $personal->fecha_ingreso?->format('Y-m-d') ?? '';
        $this->activo = $personal->activo;
    }

    public function guardarDatos(PersonalService $service): void
    {
        Gate::authorize('personal.gestionar');

        $this->validate([
            'nombres' => 'required|string|max:150',
            'apellidos' => 'required|string|max:150',
            'celular' => 'nullable|string',
            'cargo' => 'required|string|max:150',
            'area' => 'nullable|string|max:150',
            'fechaIngreso' => 'nullable|date',
            'activo' => 'boolean',
        ]);

        $this->personal = $service->actualizar($this->personal, [
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'celular' => $this->celular !== '' ? new Telefono($this->celular) : null,
            'cargo' => $this->cargo,
            'area' => $this->area !== '' ? $this->area : null,
            'fechaIngreso' => $this->fechaIngreso !== '' ? $this->fechaIngreso : null,
            'activo' => $this->activo,
        ]);

        session()->flash('status', 'Datos actualizados correctamente.');
    }

    public function with(AuditService $audit): array
    {
        return [
            'puedeGestionar' => Gate::allows('personal.gestionar'),
            'puedeVerAuditoria' => Gate::allows('auditoria.ver'),
            'historial' => Gate::allows('auditoria.ver')
                ? $audit->historialDe($this->personal)->take(10)
                : collect(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('personal.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Personal</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $personal->nombreCompleto() }}</h1>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink">Datos personales</h2>
            <x-badge :variant="$personal->activo ? 'ok' : 'neutral'">
                {{ $personal->activo ? 'Activo' : 'Inactivo' }}
            </x-badge>
        </div>

        <form wire:submit="guardarDatos" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="nombres" value="Nombres" />
                    <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apellidos" value="Apellidos" />
                    <x-text-input wire:model="apellidos" id="apellidos" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('apellidos')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="DNI" />
                    <p class="mt-1 flex h-[42px] items-center rounded-md border border-border bg-surface-2 px-3 font-mono text-sm text-ink-dim">{{ $personal->dni }}</p>
                </div>
                <div>
                    <x-input-label for="celular" value="Celular" />
                    <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="cargo" value="Cargo" />
                    <x-text-input wire:model="cargo" id="cargo" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('cargo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="area" value="Área" />
                    <x-text-input wire:model="area" id="area" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('area')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="fechaIngreso" value="Fecha de ingreso" />
                    <x-date-input wire:model="fechaIngreso" id="fechaIngreso" class="mt-1 block w-full" :disabled="! $puedeGestionar" />
                    <x-input-error :messages="$errors->get('fechaIngreso')" class="mt-1" />
                    @if ($personal->fecha_ingreso)
                        <p class="mt-1 text-xs text-ink-faint">Antigüedad: {{ $personal->fecha_ingreso->diffForHumans(null, true) }}.</p>
                    @endif
                </div>
            </div>

            @if ($puedeGestionar)
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                    Sigue activo en la institución
                </label>

                <div class="flex justify-end">
                    <x-primary-button type="submit">Guardar cambios</x-primary-button>
                </div>
            @endif
        </form>
    </div>

    @if ($puedeVerAuditoria)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Actividad reciente</h2>
            <div class="mt-4 divide-y divide-border">
                @forelse ($historial as $entrada)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span class="text-ink-dim">
                            {{ match ($entrada->event) {
                                'created' => 'Registro creado',
                                'updated' => 'Datos actualizados',
                                'deleted' => 'Registro eliminado',
                                default => $entrada->event,
                            } }}
                        </span>
                        <span class="text-xs text-ink-faint">{{ $entrada->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">Sin actividad registrada todavía.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
