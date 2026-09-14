<?php

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Imports\HojaConEncabezadosImport;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Horario $horario;

    public ?int $evaluacionId = null;

    public string $nuevoNombre = '';

    public string $nuevaFecha = '';

    public string $nuevoEnlace = '';

    public string $nuevoDisponibleHasta = '';

    public string $nuevaSemana = '';

    public bool $mostrarFormNueva = false;

    public string $enlaceEditar = '';

    public string $disponibleHastaEditar = '';

    public string $semanaEditar = '';

    /** @var array<int, string> */
    public array $notas = [];

    /** @var array<int, string> */
    public array $observaciones = [];

    public bool $guardado = false;

    public $archivoNotas = null;

    /** @var array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}|null */
    public ?array $resultadoImportacion = null;

    public function mount(Horario $horario): void
    {
        Gate::authorize('evaluaciones.ver-horario', $horario);

        $this->horario = $horario;
        $this->nuevaFecha = now()->format('Y-m-d');
    }

    public function crear(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $this->validate([
            'nuevoNombre' => 'required|string|max:150',
            'nuevaFecha' => 'required|date',
            'nuevoEnlace' => 'nullable|url|max:500',
            'nuevoDisponibleHasta' => 'nullable|date',
            'nuevaSemana' => 'nullable|integer|min:1',
        ]);

        $evaluacion = $service->crear(
            $this->horario,
            $this->nuevoNombre,
            $this->nuevaFecha,
            $this->nuevoEnlace ?: null,
            $this->nuevoDisponibleHasta ?: null,
            $this->nuevaSemana !== '' ? (int) $this->nuevaSemana : null,
        );

        $this->reset(['nuevoNombre', 'nuevoEnlace', 'nuevoDisponibleHasta', 'nuevaSemana', 'mostrarFormNueva']);
        $this->nuevaFecha = now()->format('Y-m-d');
        $this->evaluacionId = $evaluacion->id;
    }

    public function seleccionar(int $evaluacionId, EvaluacionService $service): void
    {
        $this->evaluacionId = $evaluacionId;
        $this->guardado = false;
        $this->cargarNotas($service);
    }

    private function cargarNotas(EvaluacionService $service): void
    {
        if (! $this->evaluacionId) {
            return;
        }

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->find($this->evaluacionId);

        if (! $evaluacion) {
            $this->evaluacionId = null;

            return;
        }

        $estudiantes = $service->estudiantesDelHorario($this->horario);
        $existentes = $service->calificacionesDe($evaluacion);

        $this->notas = [];
        $this->observaciones = [];
        $this->enlaceEditar = (string) $evaluacion->enlace_externo;
        $this->disponibleHastaEditar = $evaluacion->disponible_hasta?->format('Y-m-d\TH:i') ?? '';
        $this->semanaEditar = $evaluacion->semana !== null ? (string) $evaluacion->semana : '';

        foreach ($estudiantes as $estudiante) {
            $calificacion = $existentes->get($estudiante->id);
            $this->notas[$estudiante->id] = $calificacion ? (string) $calificacion->nota_numerica : '';
            $this->observaciones[$estudiante->id] = $calificacion?->observaciones ?? '';
        }
    }

    public function actualizarEnlace(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $this->validate([
            'enlaceEditar' => 'nullable|url|max:500',
            'disponibleHastaEditar' => 'nullable|date',
            'semanaEditar' => 'nullable|integer|min:1',
        ]);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $service->actualizarEnlace($evaluacion, $this->enlaceEditar ?: null, $this->disponibleHastaEditar ?: null);
        $service->actualizarSemana($evaluacion, $this->semanaEditar !== '' ? (int) $this->semanaEditar : null);
    }

    public function guardarNotas(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $rules = [];
        foreach (array_keys($this->notas) as $estudianteId) {
            $rules["notas.{$estudianteId}"] = 'nullable|numeric|min:0|max:20';
        }
        $this->validate($rules);

        $estudiantes = $service->estudiantesDelHorario($this->horario)->keyBy('id');

        foreach ($this->notas as $estudianteId => $nota) {
            if ($nota === '' || $nota === null) {
                continue;
            }

            $estudiante = $estudiantes->get($estudianteId);

            if (! $estudiante) {
                continue;
            }

            $service->calificar(
                $evaluacion,
                $estudiante,
                (float) $nota,
                $this->observaciones[$estudianteId] ?: null,
                Auth::id(),
            );
        }

        $this->guardado = true;
    }

    /**
     * Importa notas desde un CSV/Excel (típicamente exportado de un Google
     * Forms al que se le agregó una pregunta de DNI): columnas "dni" y
     * "nota" obligatorias, "observaciones" opcional. Reutiliza el mismo
     * permiso que el registro manual de notas.
     */
    public function importarNotas(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $this->validate([
            'archivoNotas' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $import = new HojaConEncabezadosImport;
        Excel::import($import, $this->archivoNotas);

        $this->resultadoImportacion = $service->calificarDesdeFilas($evaluacion, $import->filas, Auth::id());
        $this->reset('archivoNotas');
        $this->cargarNotas($service);
    }

    public function publicar(EvaluacionService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('evaluaciones.publicar'), 403);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $service->publicar($evaluacion);
    }

    /**
     * Agrupa por número de semana (clave 0 = "Bienvenida", antes de la
     * Semana 1, para el contenido sin clasificar) y ordena las semanas de
     * forma ascendente.
     *
     * @param  Collection<int, mixed>  $items
     * @return SupportCollection<int, Collection<int, mixed>>
     */
    private function agruparPorSemana($items): SupportCollection
    {
        return $items->groupBy(fn ($item) => $item->semana ?? 0)->sortKeys();
    }

    public function with(EvaluacionService $service, BloqueoAccesoService $bloqueos): array
    {
        $user = Auth::user();
        $puedeRegistrar = Gate::allows('evaluaciones.registrar-horario', $this->horario);
        $puedeSupervisar = ! $puedeRegistrar && $user->hasPermissionTo('evaluaciones.ver');
        $puedePublicar = $user->hasPermissionTo('evaluaciones.publicar');

        if ($puedeRegistrar || $puedeSupervisar) {
            $evaluaciones = $service->evaluacionesDelHorario($this->horario);
            $estudiantes = $service->estudiantesDelHorario($this->horario);
            $evaluacionSeleccionada = $this->evaluacionId
                ? $evaluaciones->firstWhere('id', $this->evaluacionId)
                : null;

            return [
                'puedeRegistrar' => $puedeRegistrar,
                'puedeSupervisar' => $puedeSupervisar,
                'puedePublicar' => $puedePublicar,
                'evaluacionesPorSemana' => $this->agruparPorSemana($evaluaciones),
                'estudiantes' => $estudiantes,
                'evaluacionSeleccionada' => $evaluacionSeleccionada,
                'misCalificaciones' => collect(),
                'promedio' => null,
                'evaluacionesConEnlacePorSemana' => collect(),
            ];
        }

        $misCalificaciones = collect();
        $promedio = null;
        $evaluacionesConEnlacePorSemana = collect();
        $estaBloqueado = false;

        if ($user->estudiante) {
            $estaBloqueado = $bloqueos->estaBloqueado($user->estudiante)
                || $bloqueos->tieneCuotasVencidasEnCicloActual($user->estudiante);

            if (! $estaBloqueado) {
                $misCalificaciones = $service->misCalificaciones($user->estudiante, $this->horario);
                $promedio = $service->promedioDelEstudiante($user->estudiante, $this->horario);
                $evaluacionesConEnlacePorSemana = $this->agruparPorSemana($service->evaluacionesConEnlaceDelHorario($this->horario));
            }
        }

        return [
            'puedeRegistrar' => false,
            'puedeSupervisar' => false,
            'puedePublicar' => false,
            'evaluacionesPorSemana' => collect(),
            'estudiantes' => collect(),
            'evaluacionSeleccionada' => null,
            'misCalificaciones' => $misCalificaciones,
            'promedio' => $promedio,
            'evaluacionesConEnlacePorSemana' => $evaluacionesConEnlacePorSemana,
            'miEstudianteId' => $user->estudiante?->id,
            'estaBloqueado' => $estaBloqueado,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('evaluaciones.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Evaluaciones</a>
        <div class="mt-1 flex items-center gap-2">
            <h1 class="font-display text-2xl text-ink">{{ $horario->curso->nombre }}</h1>
        </div>
        <p class="mt-1 text-sm text-ink-dim">
            {{ $horario->grado->nombre }} · {{ $horario->ciclo->nombre }} · {{ $horario->docente->name }}
        </p>
    </x-slot>

    @if ($puedeRegistrar || $puedeSupervisar)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
            <div>
                @if ($puedeRegistrar)
                    <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', true)" class="mb-3 w-full justify-center">
                        + Nueva evaluación
                    </x-secondary-button>
                @endif

                @if ($mostrarFormNueva)
                    <form wire:submit="crear" class="mb-4 space-y-3 rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <div>
                            <x-input-label for="nuevoNombre" value="Nombre" />
                            <x-text-input wire:model="nuevoNombre" id="nuevoNombre" class="mt-1 block w-full" placeholder="Evaluación mensual — julio" />
                            <x-input-error :messages="$errors->get('nuevoNombre')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevaFecha" value="Fecha" />
                            <x-date-input wire:model="nuevaFecha" id="nuevaFecha" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('nuevaFecha')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevoEnlace" value="Enlace externo (opcional)" />
                            <x-text-input wire:model="nuevoEnlace" id="nuevoEnlace" class="mt-1 block w-full" placeholder="https://…" />
                            <p class="mt-1 text-xs text-ink-faint">Los estudiantes lo verán recién cuando publiques la evaluación, para poder rendirla.</p>
                            <x-input-error :messages="$errors->get('nuevoEnlace')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevoDisponibleHasta" value="Disponible hasta (opcional)" />
                            <x-datetime-input wire:model="nuevoDisponibleHasta" id="nuevoDisponibleHasta" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-ink-faint">Pasada esta fecha, el enlace deja de estar disponible para el estudiante.</p>
                            <x-input-error :messages="$errors->get('nuevoDisponibleHasta')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevaSemana" value="Semana (opcional)" />
                            <x-text-input wire:model="nuevaSemana" id="nuevaSemana" type="number" min="1" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-ink-faint">Déjalo vacío para que aparezca en «Bienvenida», antes de la Semana 1.</p>
                            <x-input-error :messages="$errors->get('nuevaSemana')" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', false)">Cancelar</x-secondary-button>
                            <x-primary-button type="submit">Crear</x-primary-button>
                        </div>
                    </form>
                @endif

                <div class="space-y-4">
                    @forelse ($evaluacionesPorSemana as $numeroSemana => $evaluacionesDeSemana)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                            <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                                @foreach ($evaluacionesDeSemana as $evaluacion)
                                    <button
                                        type="button"
                                        wire:click="seleccionar({{ $evaluacion->id }})"
                                        @class([
                                            'flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm transition',
                                            'bg-accent-soft' => $evaluacionSeleccionada?->id === $evaluacion->id,
                                            'hover:bg-surface-2' => $evaluacionSeleccionada?->id !== $evaluacion->id,
                                        ])
                                    >
                                        <span>
                                            <span class="block text-ink">{{ $evaluacion->nombre }}</span>
                                            <span class="block text-xs text-ink-faint">{{ $evaluacion->fecha->format('d/m/Y') }}</span>
                                        </span>
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs',
                                            'bg-accent-soft text-accent' => $evaluacion->estaPublicada(),
                                            'bg-surface-2 text-ink-faint' => ! $evaluacion->estaPublicada(),
                                        ])>
                                            {{ $evaluacion->estado->label() }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay evaluaciones.</p>
                    @endforelse
                </div>
            </div>

            <div>
                @if ($evaluacionSeleccionada)
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="font-display text-lg text-ink">{{ $evaluacionSeleccionada->nombre }}</p>
                            <p class="text-xs text-ink-faint">{{ $evaluacionSeleccionada->fecha->format('d/m/Y') }}</p>
                        </div>
                        @if ($puedePublicar && ! $evaluacionSeleccionada->estaPublicada())
                            <x-secondary-button type="button" x-on:click="$store.confirm.preguntar('¿Publicar esta evaluación? Los estudiantes podrán ver sus notas{{ $evaluacionSeleccionada->enlace_externo ? ' y acceder al enlace para rendirla' : '' }}.', () => $wire.publicar(), { etiquetaConfirmar: 'Publicar' })">
                                Publicar
                            </x-secondary-button>
                        @endif
                    </div>

                    @if ($guardado)
                        <p class="mb-4 rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">Notas guardadas.</p>
                    @endif

                    @if ($puedeRegistrar)
                        <div class="mb-4 space-y-3 rounded-2xl border border-border bg-surface shadow-sm p-4">
                            @unless ($evaluacionSeleccionada->estaPublicada())
                                <p class="text-xs text-ink-faint">El enlace no será visible para los estudiantes hasta que publiques esta evaluación.</p>
                            @endunless
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="enlaceEditar" value="Enlace externo (opcional)" />
                                    <x-text-input wire:model="enlaceEditar" id="enlaceEditar" class="mt-1 block w-full" placeholder="https://…" />
                                    <x-input-error :messages="$errors->get('enlaceEditar')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="disponibleHastaEditar" value="Disponible hasta (opcional)" />
                                    <x-datetime-input wire:model="disponibleHastaEditar" id="disponibleHastaEditar" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('disponibleHastaEditar')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="semanaEditar" value="Semana (opcional)" />
                                    <x-text-input wire:model="semanaEditar" id="semanaEditar" type="number" min="1" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('semanaEditar')" class="mt-1" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <x-secondary-button type="button" wire:click="actualizarEnlace">Guardar enlace</x-secondary-button>
                            </div>
                        </div>
                    @elseif ($evaluacionSeleccionada->enlace_externo)
                        <p class="mb-4 text-sm text-ink-dim">
                            Enlace externo:
                            <a href="{{ $evaluacionSeleccionada->enlace_externo }}" target="_blank" class="font-medium text-accent hover:underline">{{ $evaluacionSeleccionada->enlace_externo }}</a>
                            @if ($evaluacionSeleccionada->disponible_hasta)
                                <span class="text-xs text-ink-faint">· disponible hasta {{ $evaluacionSeleccionada->disponible_hasta->format('d/m/Y H:i') }}</span>
                            @endif
                        </p>
                    @endif

                    <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                        @forelse ($estudiantes as $estudiante)
                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <p class="text-ink">{{ $estudiante->nombreCompleto() }}</p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="20"
                                        wire:model="notas.{{ $estudiante->id }}"
                                        placeholder="—"
                                        class="w-20 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"
                                        @disabled(! $puedeRegistrar)
                                    >
                                    <input
                                        type="text"
                                        wire:model="observaciones.{{ $estudiante->id }}"
                                        placeholder="Observación (opcional)"
                                        class="w-48 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                        @disabled(! $puedeRegistrar)
                                    >
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay estudiantes matriculados en este horario.</p>
                        @endforelse
                    </div>

                    @if ($puedeRegistrar && $estudiantes->isNotEmpty())
                        <div class="mt-4 flex justify-end">
                            <x-primary-button type="button" wire:click="guardarNotas">Guardar notas</x-primary-button>
                        </div>
                    @endif

                    @if ($puedeRegistrar)
                        <div class="mt-6 rounded-2xl border border-border bg-surface shadow-sm p-4">
                            <h3 class="font-display text-sm text-ink">Importar notas desde un archivo</h3>
                            <p class="mt-1 text-xs text-ink-faint">
                                Para las evaluaciones rendidas por Google Forms: agrega una pregunta de DNI al formulario y exporta las respuestas a CSV o Excel.
                                El archivo debe tener una columna <code class="rounded bg-surface-2 px-1">dni</code>, una columna <code class="rounded bg-surface-2 px-1">nota</code> (0 a 20) y, opcionalmente, <code class="rounded bg-surface-2 px-1">observaciones</code>.
                            </p>

                            <form wire:submit="importarNotas" class="mt-3 flex flex-wrap items-end gap-3">
                                <div class="flex-1">
                                    <input
                                        type="file"
                                        wire:model="archivoNotas"
                                        id="archivoNotas"
                                        accept=".xlsx,.xls,.csv"
                                        class="block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:text-ink"
                                    >
                                    <div wire:loading wire:target="archivoNotas" class="mt-1 text-xs text-ink-faint">Subiendo…</div>
                                    <x-input-error :messages="$errors->get('archivoNotas')" class="mt-1" />
                                </div>
                                <x-secondary-button type="submit" wire:loading.attr="disabled" wire:target="importarNotas">
                                    <span wire:loading.remove wire:target="importarNotas">Importar</span>
                                    <span wire:loading wire:target="importarNotas">Importando…</span>
                                </x-secondary-button>
                            </form>

                            @if ($resultadoImportacion)
                                <div class="mt-3 rounded-md border border-ok/30 bg-ok/10 px-3 py-2 text-xs text-ok">
                                    {{ $resultadoImportacion['exitosos'] }} nota{{ $resultadoImportacion['exitosos'] === 1 ? '' : 's' }} importada{{ $resultadoImportacion['exitosos'] === 1 ? '' : 's' }} correctamente.
                                </div>

                                @if (count($resultadoImportacion['errores']) > 0)
                                    <div class="mt-2">
                                        <p class="text-xs font-semibold text-danger">{{ count($resultadoImportacion['errores']) }} fila{{ count($resultadoImportacion['errores']) === 1 ? '' : 's' }} con errores:</p>
                                        <div class="mt-1 max-h-48 overflow-y-auto rounded-md border border-border">
                                            <table class="min-w-full divide-y divide-border text-xs">
                                                <tbody class="divide-y divide-border">
                                                    @foreach ($resultadoImportacion['errores'] as $error)
                                                        <tr>
                                                            <td class="px-3 py-1.5 font-mono text-ink-dim">Fila {{ $error['fila'] }}</td>
                                                            <td class="px-3 py-1.5 text-danger">{{ $error['mensaje'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                @else
                    <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
                        Selecciona una evaluación para ver o registrar notas.
                    </p>
                @endif
            </div>
        </div>
    @elseif ($estaBloqueado)
        <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-6 text-sm text-danger">
            <p class="font-medium">Tus notas no están disponibles.</p>
            <p class="mt-1">Tienes cuotas vencidas sin pagar. Regulariza tu deuda en
                <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                o comunícate con Cobranza para un compromiso de pago.</p>
        </div>
    @else
        <div class="space-y-4">
            @if ($evaluacionesConEnlacePorSemana->isNotEmpty())
                <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                    <p class="text-xs uppercase tracking-wide text-ink-faint">Evaluaciones para rendir</p>
                    <div class="mt-3 space-y-4">
                        @foreach ($evaluacionesConEnlacePorSemana as $numeroSemana => $evaluacionesDeSemana)
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                                <div class="divide-y divide-border">
                                    @foreach ($evaluacionesDeSemana as $evaluacion)
                                        <div class="flex items-center justify-between gap-4 py-2 text-sm">
                                            <div>
                                                <p class="text-ink">{{ $evaluacion->nombre }}</p>
                                                <p class="text-xs text-ink-faint">
                                                    {{ $evaluacion->fecha->format('d/m/Y') }}
                                                    @if ($evaluacion->disponible_hasta)
                                                        · disponible hasta {{ $evaluacion->disponible_hasta->format('d/m/Y H:i') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <a href="{{ $evaluacion->enlace_externo }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Abrir enlace →</a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <p class="text-xs uppercase tracking-wide text-ink-faint">Promedio del curso</p>
                <p class="mt-1 font-display text-3xl text-accent">{{ $promedio !== null ? number_format($promedio, 2) : '—' }}</p>
            </div>

            <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                @forelse ($misCalificaciones as $calificacion)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $calificacion->evaluacion->nombre }}</p>
                            <p class="text-xs text-ink-faint">{{ $calificacion->evaluacion->fecha->format('d/m/Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-display text-lg text-ink">{{ number_format((float) $calificacion->nota_numerica, 2) }}</p>
                            <p class="text-xs text-ink-faint">{{ $calificacion->notaLetra()->value }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay notas publicadas para este curso.</p>
                @endforelse
            </div>

            @if ($miEstudianteId)
                <a
                    href="{{ route('evaluaciones.libreta', ['estudiante' => $miEstudianteId, 'ciclo' => $horario->ciclo_id]) }}"
                    wire:navigate
                    class="inline-block text-sm font-medium text-accent hover:underline"
                >
                    Ver mi libreta del ciclo →
                </a>
            @endif
        </div>
    @endif
</div>
