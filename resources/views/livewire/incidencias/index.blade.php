<?php

use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Incidencias\Services\IncidenciaService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarFormNueva = false;

    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public string $tipo = '';

    public string $descripcion = '';

    public string $fecha = '';

    public string $tareaId = '';

    public string $evaluacionId = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['incidencias.ver', 'incidencias.crear', 'incidencias.gestionar_propio', 'incidencias.ver_propio']),
            403,
        );

        $this->fecha = now()->format('Y-m-d');
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';
        $this->tareaId = '';
        $this->evaluacionId = '';
    }

    public function crear(IncidenciaService $service): void
    {
        $estudiante = Estudiante::query()->findOrFail($this->estudianteSeleccionadoId);

        Gate::authorize('incidencias.reportar-estudiante', $estudiante);

        $this->validate([
            'estudianteSeleccionadoId' => 'required|integer',
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoIncidenciaEnum::cases(), 'value')),
            'descripcion' => 'required|string|max:1000',
            'fecha' => 'required|date',
            'tareaId' => 'required_if:tipo,tarea_no_realizada|nullable|integer',
            'evaluacionId' => 'required_if:tipo,evaluacion_no_realizada|nullable|integer',
        ]);

        $tipo = TipoIncidenciaEnum::from($this->tipo);
        $tarea = $this->tareaId !== '' ? Tarea::query()->findOrFail($this->tareaId) : null;
        $evaluacion = $this->evaluacionId !== '' ? Evaluacion::query()->findOrFail($this->evaluacionId) : null;

        $horario = null;
        if ($tarea) {
            $horario = $tarea->cursoVirtual->horario;
        } elseif ($evaluacion) {
            $horario = $evaluacion->horario;
        }

        $service->crear($estudiante, Auth::user(), $tipo, $this->descripcion, $this->fecha, $horario, $tarea, $evaluacion);

        $this->reset(['estudianteSeleccionadoId', 'estudianteSeleccionadoNombre', 'tipo', 'descripcion', 'tareaId', 'evaluacionId', 'mostrarFormNueva']);
        $this->fecha = now()->format('Y-m-d');
        session()->flash('status', 'Incidencia registrada.');
    }

    public function with(IncidenciaService $service): array
    {
        $user = Auth::user();
        $puedeVerTodas = $user->hasPermissionTo('incidencias.ver');
        $puedeGestionarPropio = $user->hasPermissionTo('incidencias.gestionar_propio');
        $puedeCrear = $user->hasPermissionTo('incidencias.crear') || $puedeGestionarPropio;
        $puedeVerPropio = $user->hasPermissionTo('incidencias.ver_propio');

        $incidencias = match (true) {
            $puedeVerTodas => $service->todas(),
            $puedeGestionarPropio => $service->delDocente($user),
            $puedeVerPropio && $user->estudiante => $service->delEstudiante($user->estudiante),
            default => collect(),
        };

        $resultadosBusqueda = collect();
        if ($this->terminoBusqueda !== '' && $puedeCrear) {
            $resultadosBusqueda = $puedeVerTodas
                ? $service->buscar($this->terminoBusqueda)
                : $service->estudiantesDelDocente($user)->filter(function (Estudiante $estudiante) {
                    $termino = mb_strtolower($this->terminoBusqueda);

                    return str_contains(mb_strtolower($estudiante->nombreCompleto()), $termino)
                        || str_contains($estudiante->dni, $termino);
                })->take(8);
        }

        $tareasNoRealizadas = collect();
        $evaluacionesNoRealizadas = collect();
        if ($this->estudianteSeleccionadoId) {
            $estudianteSeleccionado = Estudiante::query()->find($this->estudianteSeleccionadoId);

            if ($estudianteSeleccionado) {
                if ($this->tipo === TipoIncidenciaEnum::TAREA_NO_REALIZADA->value) {
                    $tareasNoRealizadas = $service->tareasNoRealizadasDe($estudianteSeleccionado);
                }

                if ($this->tipo === TipoIncidenciaEnum::EVALUACION_NO_REALIZADA->value) {
                    $evaluacionesNoRealizadas = $service->evaluacionesNoRealizadasDe($estudianteSeleccionado);
                }
            }
        }

        return [
            'puedeVerTodas' => $puedeVerTodas,
            'puedeGestionarPropio' => $puedeGestionarPropio,
            'puedeCrear' => $puedeCrear,
            'puedeVerPropio' => $puedeVerPropio,
            'esVistaEstudiante' => ! $puedeVerTodas && ! $puedeGestionarPropio && $puedeVerPropio,
            'incidencias' => $incidencias,
            'resultadosBusqueda' => $resultadosBusqueda,
            'tiposIncidencia' => TipoIncidenciaEnum::cases(),
            'tareasNoRealizadas' => $tareasNoRealizadas,
            'evaluacionesNoRealizadas' => $evaluacionesNoRealizadas,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Incidencias</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($esVistaEstudiante)
                Historial de incidencias registradas a tu nombre.
            @else
                Registro de incidencias de conducta, disciplina y tareas o evaluaciones no realizadas.
            @endif
        </p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($puedeCrear)
        <div class="mb-6">
            @unless ($mostrarFormNueva)
                <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', true)">+ Reportar incidencia</x-secondary-button>
            @endunless

            @if ($mostrarFormNueva)
                <form wire:submit="crear" class="mt-3 max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <div>
                        <x-input-label value="Estudiante" />
                        @if ($estudianteSeleccionadoId)
                            <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                                {{ $estudianteSeleccionadoNombre }}
                                <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
                            </div>
                        @else
                            <x-text-input wire:model.live.debounce.300ms="terminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre, apellido o DNI…" />
                            @if ($resultadosBusqueda->isNotEmpty())
                                <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                                    @foreach ($resultadosBusqueda as $estudiante)
                                        <button
                                            type="button"
                                            wire:click="seleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                        >
                                            {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        <x-input-error :messages="$errors->get('estudianteSeleccionadoId')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="tipo" value="Tipo de incidencia" />
                        <x-select-input
                            wire:model.live="tipo"
                            id="tipo"
                            class="mt-1 block w-full"
                            :options="collect($tiposIncidencia)->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                        />
                        <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                    </div>

                    @if ($tipo === 'tarea_no_realizada')
                        <div>
                            <x-input-label for="tareaId" value="Tarea no realizada" />
                            @if ($estudianteSeleccionadoId && $tareasNoRealizadas->isEmpty())
                                <p class="mt-1 text-xs text-ink-faint">Este estudiante no tiene tareas vencidas pendientes de entrega.</p>
                            @else
                                <x-select-input
                                    wire:model="tareaId"
                                    id="tareaId"
                                    class="mt-1 block w-full"
                                    :options="collect($tareasNoRealizadas)->mapWithKeys(fn ($t) => [$t->id => $t->titulo.' ('.$t->fecha_limite->format('d/m/Y').')'])"
                                />
                            @endif
                            <x-input-error :messages="$errors->get('tareaId')" class="mt-1" />
                        </div>
                    @endif

                    @if ($tipo === 'evaluacion_no_realizada')
                        <div>
                            <x-input-label for="evaluacionId" value="Evaluación no realizada" />
                            @if ($estudianteSeleccionadoId && $evaluacionesNoRealizadas->isEmpty())
                                <p class="mt-1 text-xs text-ink-faint">Este estudiante no tiene evaluaciones publicadas pendientes de calificación.</p>
                            @else
                                <x-select-input
                                    wire:model="evaluacionId"
                                    id="evaluacionId"
                                    class="mt-1 block w-full"
                                    :options="collect($evaluacionesNoRealizadas)->mapWithKeys(fn ($e) => [$e->id => $e->nombre.' ('.$e->fecha->format('d/m/Y').')'])"
                                />
                            @endif
                            <x-input-error :messages="$errors->get('evaluacionId')" class="mt-1" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-date-input wire:model="fecha" id="fecha" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="descripcion" value="Descripción" />
                        <textarea wire:model="descripcion" id="descripcion" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                        <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Registrar</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($incidencias as $incidencia)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-badge variant="neutral">{{ $incidencia->tipo->label() }}</x-badge>
                        @if (! $esVistaEstudiante)
                            <span class="text-sm font-medium text-ink">{{ $incidencia->estudiante?->nombreCompleto() ?? '—' }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-ink-faint">{{ $incidencia->fecha->format('d/m/Y') }}</span>
                </div>

                <p class="mt-2 text-sm text-ink-dim">{{ $incidencia->descripcion }}</p>

                @if ($incidencia->tarea)
                    <p class="mt-1 text-xs text-ink-faint">Tarea: {{ $incidencia->tarea->titulo }}</p>
                @endif

                @if ($incidencia->evaluacion)
                    <p class="mt-1 text-xs text-ink-faint">Evaluación: {{ $incidencia->evaluacion->nombre }}</p>
                @endif

                <div class="mt-3 flex items-center justify-between text-xs text-ink-faint">
                    <span>Reportado por {{ $incidencia->reportadoPor?->name ?? 'Usuario eliminado' }}</span>
                    @if ($incidencia->notificado_apoderado_en)
                        <span class="text-accent">Apoderado notificado</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
                Todavía no hay incidencias registradas.
            </p>
        @endforelse
    </div>
</div>
