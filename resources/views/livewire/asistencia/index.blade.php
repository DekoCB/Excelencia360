<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Shared\Enums\RolEnum;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $cicloId = null;

    public ?string $seccion = null;

    public ?int $gradoId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio']),
            403,
        );
    }

    public function seleccionarGrupo(int $cicloId): void
    {
        $this->cicloId = $cicloId;
    }

    public function seleccionarSeccion(string $letra): void
    {
        $this->seccion = $letra;
    }

    public function seleccionarGrado(int $gradoId): void
    {
        $this->gradoId = $gradoId;
    }

    public function volverAGrupos(): void
    {
        $this->cicloId = null;
        $this->seccion = null;
        $this->gradoId = null;
    }

    public function volverASecciones(): void
    {
        $this->seccion = null;
        $this->gradoId = null;
    }

    public function volverAGrados(): void
    {
        $this->gradoId = null;
    }

    public function with(AsistenciaService $service): array
    {
        $user = Auth::user();

        // hasPermissionTo() no basta: Dirección tiene este permiso vía '*'
        // sin ser realmente docente, y quedaría viendo "sus" horarios
        // (ninguno) en vez de la vista de supervisión.
        if ($user->hasPermissionTo('asistencia.registrar') && $user->hasRole(RolEnum::DOCENTE->value)) {
            $horarios = $service->horariosDelDocente($user->id);
            $rol = 'docente';
        } elseif ($user->hasPermissionTo('asistencia.ver_propio') && $user->estudiante) {
            $horarios = $service->horariosDelEstudiante($user->estudiante);
            $rol = 'estudiante';
        } else {
            $horarios = $service->todos();
            $rol = 'supervisor';
        }

        $gruposDisponibles = $horarios->pluck('ciclo')->unique('id')->sortByDesc('fecha_inicio')->values();

        $gradosPorSeccion = collect();
        $gradosDisponibles = collect();
        $grupos = collect();

        if ($this->cicloId) {
            $delCiclo = $horarios->where('ciclo_id', $this->cicloId);
            $gradosPorSeccion = $delCiclo->pluck('grado')->unique('id')->sortBy('orden')->values()->groupBy(fn ($grado) => $grado->letraAula());

            if ($this->seccion) {
                $gradosDisponibles = $gradosPorSeccion->get($this->seccion, collect());

                if ($this->gradoId) {
                    $delGrado = $delCiclo->where('grado_id', $this->gradoId);

                    // Un mismo curso+ciclo normalmente tiene un único Horario,
                    // pero se agrupan por curso+ciclo por si dos docentes
                    // llegaran a dictarlo en paralelo: quien mira elegiría
                    // cuál quiere ver en vez de toparse con dos tarjetas casi
                    // idénticas.
                    $grupos = $delGrado->groupBy('curso_id');
                }
            }
        }

        return [
            'gruposDisponibles' => $gruposDisponibles,
            'gradosPorSeccion' => $gradosPorSeccion,
            'gradosDisponibles' => $gradosDisponibles,
            'grupos' => $grupos,
            'rol' => $rol,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Asistencia</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Toma asistencia en tus horarios asignados este ciclo.
            @elseif ($rol === 'estudiante')
                Tu asistencia en los cursos donde estás matriculado.
            @else
                Supervisión de asistencia de todos los horarios.
            @endif
        </p>
    </x-slot>

    @if ($rol === 'estudiante')
        <div class="mb-4">
            <a
                href="{{ route('asistencia.marcar') }}"
                wire:navigate
                class="flex items-center justify-between gap-3 rounded-lg border border-accent/30 bg-accent-soft px-4 py-3 transition hover:border-accent"
            >
                <span class="flex items-center gap-3">
                    <x-heroicon-o-qr-code class="h-6 w-6 shrink-0 text-accent" />
                    <span>
                        <span class="block text-sm font-semibold text-accent">Marcar mi asistencia</span>
                        <span class="block text-xs text-ink-dim">Confirma tu DNI si tienes clase ahora mismo.</span>
                    </span>
                </span>
                <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-accent" />
            </a>
        </div>
    @endif

    @if (! $cicloId)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($gruposDisponibles as $grupo)
                <button
                    type="button"
                    wire:click="seleccionarGrupo({{ $grupo->id }})"
                    class="block rounded-2xl border border-border bg-surface shadow-sm p-4 text-left transition hover:border-accent"
                >
                    <p class="font-display text-lg text-ink">{{ $grupo->nombre }}</p>
                    <p class="mt-1 text-sm text-ink-dim">{{ $grupo->fecha_inicio->format('d/m/Y') }} – {{ $grupo->fecha_fin->format('d/m/Y') }}</p>
                </button>
            @empty
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">
                    @if ($rol === 'docente')
                        Todavía no tienes horarios asignados este ciclo.
                    @elseif ($rol === 'estudiante')
                        Todavía no hay horarios para tu matrícula actual.
                    @else
                        No hay horarios registrados.
                    @endif
                </p>
            @endforelse
        </div>
    @elseif (! $seccion)
        <button type="button" wire:click="volverAGrupos" class="mb-4 text-sm text-ink-faint hover:text-ink">← Grupos</button>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach (['A', 'B'] as $letra)
                <button
                    type="button"
                    wire:click="seleccionarSeccion('{{ $letra }}')"
                    class="block rounded-2xl border border-border bg-surface shadow-sm p-6 text-center transition hover:border-accent"
                >
                    <p class="font-display text-lg text-ink">Sección {{ $letra }}</p>
                    <p class="mt-1 text-sm text-ink-dim">{{ $gradosPorSeccion->get($letra, collect())->pluck('nombre')->implode(', ') ?: 'Sin horarios en este grupo' }}</p>
                </button>
            @endforeach
        </div>
    @elseif (! $gradoId)
        <button type="button" wire:click="volverASecciones" class="mb-4 text-sm text-ink-faint hover:text-ink">← Secciones</button>

        <div class="grid grid-cols-2 gap-4">
            @forelse ($gradosDisponibles as $grado)
                <button
                    type="button"
                    wire:click="seleccionarGrado({{ $grado->id }})"
                    class="block rounded-2xl border border-border bg-surface shadow-sm p-6 text-center transition hover:border-accent"
                >
                    <p class="font-display text-lg text-ink">{{ $grado->nombre }}</p>
                </button>
            @empty
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Esta sección no tiene horarios en este grupo.</p>
            @endforelse
        </div>
    @else
        <button type="button" wire:click="volverAGrados" class="mb-4 text-sm text-ink-faint hover:text-ink">← Grados</button>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($grupos as $grupo)
                @php $primero = $grupo->first(); @endphp
                @if ($grupo->count() === 1)
                    <a
                        href="{{ route('asistencia.show', $primero) }}"
                        wire:navigate
                        class="block rounded-2xl border border-border bg-surface shadow-sm p-4 transition hover:border-accent"
                    >
                        <p class="font-display text-lg text-ink">{{ $primero->curso->nombre }}</p>
                        <p class="mt-3 text-xs text-ink-faint">
                            @if ($rol !== 'docente')
                                {{ $primero->docente->name }} ·
                            @endif
                            {{ $primero->diasResumen() }}
                        </p>
                    </a>
                @else
                    <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <p class="font-display text-lg text-ink">{{ $primero->curso->nombre }}</p>

                        <p class="mt-3 text-xs text-ink-faint">Selecciona un horario</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($grupo->sortBy(fn ($opcion) => $opcion->docente->name) as $opcion)
                                <a
                                    href="{{ route('asistencia.show', $opcion) }}"
                                    wire:navigate
                                    class="rounded-full border border-border bg-surface-2 px-3 py-1 text-xs font-medium text-ink transition hover:border-accent hover:text-accent"
                                >
                                    {{ $opcion->docente->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @empty
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Este grado no tiene horarios en este grupo.</p>
            @endforelse
        </div>
    @endif
</div>
