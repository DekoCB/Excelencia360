<?php

use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Services\AsistenciaDocenteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Asistencia laboral de docentes, un registro por día (no por sesión de
 * clase -- eso ya lo cubre Asistencia de estudiantes). Quien tiene
 * asistencia_docentes.registrar marca el día de todos los docentes; quien
 * solo tiene asistencia_docentes.ver_propio ve su propio historial.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $fecha = '';

    /** @var array<int, string> */
    public array $estados = [];

    /** @var array<int, string> */
    public array $observaciones = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null> */
    public array $justificantes = [];

    public function mount(): void
    {
        abort_unless(
            Auth::user()->hasAnyPermission(['asistencia_docentes.ver', 'asistencia_docentes.registrar', 'asistencia_docentes.ver_propio']),
            403,
        );

        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedFecha(): void
    {
        $this->reset(['estados', 'observaciones', 'justificantes']);
    }

    public function guardar(AsistenciaDocenteService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_docentes.registrar'), 403);

        $this->validate([
            'fecha' => 'required|date',
            'estados.*' => 'required|string|in:'.implode(',', array_column(EstadoAsistenciaEnum::cases(), 'value')),
        ]);

        $service->registrar(Auth::user(), $this->fecha, $this->estados, $this->observaciones, $this->justificantes);

        $this->reset(['observaciones', 'justificantes']);
        session()->flash('status', 'Asistencia registrada.');
    }

    public function with(AsistenciaDocenteService $service): array
    {
        $user = Auth::user();
        $puedeVerTodo = $user->hasAnyPermission(['asistencia_docentes.ver', 'asistencia_docentes.registrar']);

        if ($puedeVerTodo) {
            $docentes = $service->docentesActivos();
            $registrosDelDia = $service->deDia($this->fecha);

            foreach ($docentes as $docente) {
                if (! isset($this->estados[$docente->id])) {
                    $this->estados[$docente->id] = $registrosDelDia->get($docente->id)?->estado->value ?? EstadoAsistenciaEnum::PRESENTE->value;
                }
            }

            return [
                'puedeRegistrar' => $user->hasPermissionTo('asistencia_docentes.registrar'),
                'puedeVerTodo' => true,
                'docentes' => $docentes,
                'estadosDisponibles' => EstadoAsistenciaEnum::cases(),
                'historial' => collect(),
                'resumen' => null,
            ];
        }

        $docentePropio = $user->docente;
        $historial = $docentePropio ? $service->historialDocente($docentePropio) : collect();
        $resumen = $docentePropio ? $service->resumenDocente($docentePropio) : null;

        return [
            'puedeRegistrar' => false,
            'puedeVerTodo' => false,
            'docentes' => collect(),
            'estadosDisponibles' => EstadoAsistenciaEnum::cases(),
            'historial' => $historial,
            'resumen' => $resumen,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Asistencia de docentes</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($puedeVerTodo)
                Control diario de asistencia laboral del personal docente.
            @else
                Tu historial de asistencia de los últimos meses.
            @endif
        </p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($puedeVerTodo)
        <div class="mb-4">
            <x-input-label for="fecha" value="Fecha" />
            <x-text-input wire:model.live="fecha" id="fecha" type="date" class="mt-1 block w-auto" />
        </div>

        <form wire:submit="guardar" class="space-y-3">
            <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                @forelse ($docentes as $docente)
                    <div class="flex flex-wrap items-center gap-3 border-b border-border p-4 last:border-b-0">
                        <div class="min-w-[10rem] flex-1">
                            <p class="text-sm font-semibold text-ink">{{ $docente->usuario->name }}</p>
                            @if ($docente->especialidad)
                                <p class="text-xs text-ink-faint">{{ $docente->especialidad }}</p>
                            @endif
                        </div>

                        <x-select-input
                            wire:model="estados.{{ $docente->id }}"
                            class="w-40"
                            :options="collect($estadosDisponibles)->mapWithKeys(fn ($e) => [$e->value => $e->label()])"
                            :disabled="! $puedeRegistrar"
                        />

                        @if ($puedeRegistrar)
                            <x-text-input
                                wire:model="observaciones.{{ $docente->id }}"
                                type="text"
                                class="w-56"
                                placeholder="Observación (opcional)"
                            />
                            <input type="file" wire:model="justificantes.{{ $docente->id }}" class="w-48 text-xs text-ink-dim file:mr-2 file:rounded-md file:border-0 file:bg-surface-2 file:px-2 file:py-1 file:text-xs file:text-ink">
                        @endif

                        <x-input-error :messages="$errors->get('estados.'.$docente->id)" class="w-full" />
                    </div>
                @empty
                    <p class="p-8 text-center text-sm text-ink-faint">No hay docentes registrados todavía.</p>
                @endforelse
            </div>

            @if ($puedeRegistrar && $docentes->isNotEmpty())
                <div class="flex justify-end">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="guardar">Guardar asistencia del día</x-primary-button>
                </div>
            @endif
        </form>
    @else
        @if ($resumen)
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-surface p-4 text-center">
                    <p class="text-2xl font-semibold text-ink">{{ $resumen['porcentaje'] }}%</p>
                    <p class="text-xs text-ink-faint">Asistencia</p>
                </div>
                @foreach ($estadosDisponibles as $estado)
                    <div class="rounded-xl border border-border bg-surface p-4 text-center">
                        <p class="text-2xl font-semibold text-ink">{{ $resumen['por_estado'][$estado->value] ?? 0 }}</p>
                        <p class="text-xs text-ink-faint">{{ $estado->label() }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="space-y-2">
            @forelse ($historial as $registro)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface p-3">
                    <span class="text-sm text-ink">{{ Carbon::parse($registro->fecha)->format('d/m/Y') }}</span>
                    <x-badge variant="{{ match($registro->estado->value) {
                        'presente' => 'ok',
                        'tardanza' => 'warn',
                        'falta' => 'danger',
                        default => 'info',
                    } }}">{{ $registro->estado->label() }}</x-badge>
                    @if ($registro->observacion)
                        <span class="text-xs text-ink-faint">{{ $registro->observacion }}</span>
                    @endif
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">Todavía no tienes registros de asistencia.</p>
            @endforelse
        </div>
    @endif
</div>
