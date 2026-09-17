<?php

use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Shared\Enums\RolEnum;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $programaEstudioId = null;

    public ?int $gradoId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio']),
            403,
        );
    }

    public function seleccionarPrograma(int $programaEstudioId): void
    {
        $this->programaEstudioId = $programaEstudioId;
    }

    public function seleccionarGrado(int $gradoId): void
    {
        $this->gradoId = $gradoId;
    }

    public function volverAProgramas(): void
    {
        $this->programaEstudioId = null;
        $this->gradoId = null;
    }

    public function volverASemestres(): void
    {
        $this->gradoId = null;
    }

    public function with(CursoVirtualService $service): array
    {
        $user = Auth::user();

        // hasPermissionTo() no basta: Dirección tiene este permiso vía '*'
        // sin ser realmente docente, y quedaría viendo "sus" cursos (ninguno)
        // en vez de la vista de supervisión.
        if ($user->hasPermissionTo('aula_virtual.gestionar_propio') && $user->hasRole(RolEnum::DOCENTE->value)) {
            $cursos = $service->delDocente($user->id);
            $rol = 'docente';
        } elseif ($user->hasPermissionTo('aula_virtual.ver_propio') && $user->estudiante) {
            $cursos = $service->delEstudiante($user->estudiante);
            $rol = 'estudiante';
        } else {
            $cursos = $service->todos();
            $rol = 'supervisor';
        }

        // $cursos ya viene acotado al período de matrícula actual (ver
        // CursoVirtualService::cicloActualId()) -- acá solo queda agrupar
        // por Programa de Estudio y Semestre, sin pedirle al usuario que
        // elija el período a mano.
        $programasDisponibles = $cursos->pluck('horario.grado.programaEstudio')->unique('id')->sortBy('nombre')->values();

        $gradosDisponibles = collect();
        $grupos = collect();

        if ($this->programaEstudioId) {
            $delPrograma = $cursos->filter(fn ($curso) => $curso->horario->grado->programa_estudio_id === $this->programaEstudioId);
            $gradosDisponibles = $delPrograma->pluck('horario.grado')->unique('id')->sortBy('orden')->values();

            if ($this->gradoId) {
                $delGrado = $delPrograma->filter(fn ($curso) => $curso->horario->grado_id === $this->gradoId);

                // Un mismo curso+ciclo normalmente tiene un único Horario y
                // por lo tanto un único CursoVirtual, pero se agrupan por
                // curso por si dos docentes llegaran a dictarlo en
                // paralelo: quien mira elegiría cuál quiere ver en vez de
                // toparse con dos tarjetas casi idénticas sin ninguna
                // pista de cuál es cuál.
                $grupos = $delGrado->groupBy(fn ($curso) => $curso->horario->curso_id);
            }
        }

        return [
            'programasDisponibles' => $programasDisponibles,
            'gradosDisponibles' => $gradosDisponibles,
            'grupos' => $grupos,
            'rol' => $rol,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Aula Virtual</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Tus cursos asignados este período.
            @elseif ($rol === 'estudiante')
                Los cursos donde estás matriculado.
            @else
                Todos los cursos virtuales activos este período.
            @endif
        </p>
    </x-slot>

    @if (! $programaEstudioId)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($programasDisponibles as $programa)
                <button
                    type="button"
                    wire:click="seleccionarPrograma({{ $programa->id }})"
                    class="block rounded-2xl border border-border bg-surface shadow-sm p-4 text-left transition hover:border-accent"
                >
                    <p class="font-display text-lg text-ink">{{ $programa->nombre }}</p>
                </button>
            @empty
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">
                    @if ($rol === 'docente')
                        Todavía no tienes cursos con aula virtual activada.
                    @elseif ($rol === 'estudiante')
                        Todavía no hay cursos virtuales para tu matrícula actual.
                    @else
                        No hay cursos virtuales activos.
                    @endif
                </p>
            @endforelse
        </div>
    @elseif (! $gradoId)
        <button type="button" wire:click="volverAProgramas" class="mb-4 text-sm text-ink-faint hover:text-ink">← Programa de Estudio</button>

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
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Este programa no tiene cursos virtuales todavía.</p>
            @endforelse
        </div>
    @else
        <button type="button" wire:click="volverASemestres" class="mb-4 text-sm text-ink-faint hover:text-ink">← Semestres</button>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($grupos as $grupo)
                @php $primero = $grupo->first(); @endphp
                @if ($grupo->count() === 1)
                    <a
                        href="{{ route('aula-virtual.show', $primero) }}"
                        wire:navigate
                        class="block rounded-2xl border border-border bg-surface shadow-sm p-4 transition hover:border-accent"
                    >
                        <x-curso-portada :curso="$primero->horario->curso" class="mb-3" />
                        <p class="font-display text-lg text-ink">{{ $primero->horario->curso->nombre }}</p>
                        <p class="mt-3 text-xs text-ink-faint">
                            @if ($rol !== 'docente')
                                {{ $primero->horario->docente->name }} ·
                            @endif
                            {{ $primero->horario->diasResumen() }}
                        </p>
                    </a>
                @else
                    <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <x-curso-portada :curso="$primero->horario->curso" class="mb-3" />
                        <p class="font-display text-lg text-ink">{{ $primero->horario->curso->nombre }}</p>

                        <p class="mt-3 text-xs text-ink-faint">Selecciona un curso virtual</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($grupo->sortBy(fn ($opcion) => $opcion->horario->docente->name) as $opcion)
                                <a
                                    href="{{ route('aula-virtual.show', $opcion) }}"
                                    wire:navigate
                                    class="rounded-full border border-border bg-surface-2 px-3 py-1 text-xs font-medium text-ink transition hover:border-accent hover:text-accent"
                                >
                                    {{ $opcion->horario->docente->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @empty
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Este semestre no tiene cursos virtuales todavía.</p>
            @endforelse
        </div>
    @endif
</div>
