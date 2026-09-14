<?php

use App\Modules\AulaVirtual\Services\CursoVirtualService;
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
            $user->hasAnyPermission(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio']),
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

        $gruposDisponibles = $cursos->pluck('horario.ciclo')->unique('id')->sortByDesc('fecha_inicio')->values();

        $gradosPorSeccion = collect();
        $gradosDisponibles = collect();
        $grupos = collect();

        if ($this->cicloId) {
            $delCiclo = $cursos->filter(fn ($curso) => $curso->horario->ciclo_id === $this->cicloId);
            $gradosPorSeccion = $delCiclo->pluck('horario.grado')->unique('id')->sortBy('orden')->values()->groupBy(fn ($grado) => $grado->letraAula());

            if ($this->seccion) {
                $gradosDisponibles = $gradosPorSeccion->get($this->seccion, collect());

                if ($this->gradoId) {
                    $delGrado = $delCiclo->filter(fn ($curso) => $curso->horario->grado_id === $this->gradoId);

                    // Un mismo curso+ciclo normalmente tiene un único Horario y
                    // por lo tanto un único CursoVirtual, pero se agrupan por
                    // curso por si dos docentes llegaran a dictarlo en
                    // paralelo: quien mira elegiría cuál quiere ver en vez de
                    // toparse con dos tarjetas casi idénticas sin ninguna
                    // pista de cuál es cuál.
                    $grupos = $delGrado->groupBy(fn ($curso) => $curso->horario->curso_id);
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
        <h1 class="font-display text-2xl text-ink">Aula Virtual</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Tus cursos asignados este ciclo.
            @elseif ($rol === 'estudiante')
                Los cursos donde estás matriculado.
            @else
                Todos los cursos virtuales activos.
            @endif
        </p>
    </x-slot>

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
                        Todavía no tienes cursos con aula virtual activada.
                    @elseif ($rol === 'estudiante')
                        Todavía no hay cursos virtuales para tu matrícula actual.
                    @else
                        No hay cursos virtuales activos.
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
                    <p class="mt-1 text-sm text-ink-dim">{{ $gradosPorSeccion->get($letra, collect())->pluck('nombre')->implode(', ') ?: 'Sin cursos en este grupo' }}</p>
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
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Esta sección no tiene cursos virtuales en este grupo.</p>
            @endforelse
        </div>
    @else
        <button type="button" wire:click="volverAGrados" class="mb-4 text-sm text-ink-faint hover:text-ink">← Grados</button>

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
                <p class="col-span-full py-8 text-center text-sm text-ink-faint">Este grado no tiene cursos virtuales en este grupo.</p>
            @endforelse
        </div>
    @endif
</div>
