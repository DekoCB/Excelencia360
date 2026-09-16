<?php

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Imports\HojaConEncabezadosImport;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\RespuestaEstudiante;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Evaluaciones\Services\IntentoEvaluacionService;
use App\Modules\Evaluaciones\Services\PreguntaService;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public CursoVirtual $curso;

    public Evaluacion $evaluacion;

    // Notas (Física)
    /** @var array<int, string> */
    public array $notas = [];

    /** @var array<int, string> */
    public array $observaciones = [];

    public bool $guardado = false;

    public $archivoNotas = null;

    /** @var array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}|null */
    public ?array $resultadoImportacion = null;

    public string $enlaceEditar = '';

    public string $disponibleHastaEditar = '';

    // Banco de preguntas (Virtual, mientras esté en borrador)
    public bool $mostrarFormPregunta = false;

    public ?int $preguntaEditandoId = null;

    public string $preguntaEnunciado = '';

    public string $preguntaTipo = '';

    public string $preguntaPuntaje = '4';

    /** @var list<string> */
    public array $alternativasTexto = ['', ''];

    /** @var list<bool> */
    public array $alternativasCorrectas = [false, false];

    // Rendir la evaluación (Virtual, estudiante)
    /** @var array<int, list<int>> */
    public array $respuestasMultiples = [];

    /** @var array<int, string> */
    public array $respuestasUnicas = [];

    /** @var array<int, string> */
    public array $respuestasTexto = [];

    // Calificar preguntas abiertas (Virtual, docente)
    /** @var array<int, string> */
    public array $puntajesAbiertas = [];

    public function mount(CursoVirtual $curso, Evaluacion $evaluacion, EvaluacionService $service): void
    {
        Gate::authorize('view', $curso);

        abort_unless($evaluacion->curso_virtual_id === $curso->id, 404);

        $this->curso = $curso;
        $this->evaluacion = $evaluacion;

        if (Gate::allows('manage', $curso) && $evaluacion->tipo === TipoEvaluacionEnum::FISICO) {
            $this->cargarNotas($service);
        }
    }

    private function cargarNotas(EvaluacionService $service): void
    {
        $estudiantes = $service->estudiantesDelHorario($this->curso->horario);
        $existentes = $service->calificacionesDe($this->evaluacion);

        foreach ($estudiantes as $estudiante) {
            $calificacion = $existentes->get($estudiante->id);
            $this->notas[$estudiante->id] = $calificacion ? (string) $calificacion->nota_numerica : '';
            $this->observaciones[$estudiante->id] = $calificacion?->observaciones ?? '';
        }

        $this->enlaceEditar = (string) $this->evaluacion->enlace_externo;
        $this->disponibleHastaEditar = $this->evaluacion->disponible_hasta?->format('Y-m-d\TH:i') ?? '';
    }

    public function guardarNotas(EvaluacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $rules = [];
        foreach (array_keys($this->notas) as $estudianteId) {
            $rules["notas.{$estudianteId}"] = 'nullable|numeric|min:0|max:20';
        }
        $this->validate($rules);

        $estudiantes = $service->estudiantesDelHorario($this->curso->horario)->keyBy('id');

        foreach ($this->notas as $estudianteId => $nota) {
            if ($nota === '' || $nota === null) {
                continue;
            }

            $estudiante = $estudiantes->get($estudianteId);

            if (! $estudiante) {
                continue;
            }

            $service->calificar($this->evaluacion, $estudiante, (float) $nota, $this->observaciones[$estudianteId] ?: null, Auth::id());
        }

        $this->guardado = true;
    }

    public function actualizarEnlace(EvaluacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'enlaceEditar' => 'nullable|url|max:500',
            'disponibleHastaEditar' => 'nullable|date',
        ]);

        $service->actualizarEnlace($this->evaluacion, $this->enlaceEditar ?: null, $this->disponibleHastaEditar ?: null);
    }

    public function importarNotas(EvaluacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'archivoNotas' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $import = new HojaConEncabezadosImport;
        Excel::import($import, $this->archivoNotas);

        $this->resultadoImportacion = $service->calificarDesdeFilas($this->evaluacion, $import->filas, Auth::id());
        $this->reset('archivoNotas');
        $this->cargarNotas($service);
    }

    public function publicar(EvaluacionService $service): void
    {
        Gate::authorize('manage', $this->curso);
        abort_unless(Auth::user()->hasPermissionTo('evaluaciones.publicar'), 403);
        abort_if($this->evaluacion->esVirtual() && $this->evaluacion->preguntas()->count() === 0, 403);

        $service->publicar($this->evaluacion);
    }

    public function abrirFormPregunta(?int $preguntaId = null): void
    {
        Gate::authorize('manage', $this->curso);
        abort_if($this->evaluacion->estaPublicada(), 403);

        $pregunta = $preguntaId ? $this->evaluacion->preguntas()->with('alternativas')->find($preguntaId) : null;

        $this->preguntaEditandoId = $pregunta?->id;
        $this->preguntaEnunciado = $pregunta?->enunciado ?? '';
        $this->preguntaTipo = $pregunta?->tipo->value ?? '';
        $this->preguntaPuntaje = $pregunta ? (string) $pregunta->puntaje : '4';
        $this->alternativasTexto = $pregunta ? $pregunta->alternativas->pluck('texto')->all() : ['', ''];
        $this->alternativasCorrectas = $pregunta ? $pregunta->alternativas->pluck('es_correcta')->all() : [false, false];
        $this->mostrarFormPregunta = true;
    }

    public function cerrarFormPregunta(): void
    {
        $this->reset(['mostrarFormPregunta', 'preguntaEditandoId', 'preguntaEnunciado', 'preguntaTipo', 'preguntaPuntaje', 'alternativasTexto', 'alternativasCorrectas']);
        $this->alternativasTexto = ['', ''];
        $this->alternativasCorrectas = [false, false];
        $this->resetErrorBag();
    }

    public function agregarAlternativa(): void
    {
        $this->alternativasTexto[] = '';
        $this->alternativasCorrectas[] = false;
    }

    public function quitarAlternativa(int $indice): void
    {
        unset($this->alternativasTexto[$indice], $this->alternativasCorrectas[$indice]);
        $this->alternativasTexto = array_values($this->alternativasTexto);
        $this->alternativasCorrectas = array_values($this->alternativasCorrectas);
    }

    public function guardarPregunta(PreguntaService $service): void
    {
        Gate::authorize('manage', $this->curso);
        abort_if($this->evaluacion->estaPublicada(), 403);

        $this->validate([
            'preguntaEnunciado' => 'required|string',
            'preguntaTipo' => 'required|string|in:'.implode(',', array_column(TipoPreguntaEnum::cases(), 'value')),
            'preguntaPuntaje' => 'required|numeric|min:0.01',
        ]);

        $tipo = TipoPreguntaEnum::from($this->preguntaTipo);

        $alternativas = [];
        if ($tipo->requiereAlternativas()) {
            foreach ($this->alternativasTexto as $indice => $texto) {
                if (trim($texto) === '') {
                    continue;
                }

                $alternativas[] = ['texto' => $texto, 'es_correcta' => (bool) ($this->alternativasCorrectas[$indice] ?? false)];
            }
        }

        if ($this->preguntaEditandoId) {
            $pregunta = $this->evaluacion->preguntas()->findOrFail($this->preguntaEditandoId);
            $service->actualizar($pregunta, $this->preguntaEnunciado, (float) $this->preguntaPuntaje, $alternativas);
        } else {
            $service->agregar($this->evaluacion, $tipo, $this->preguntaEnunciado, (float) $this->preguntaPuntaje, $alternativas);
        }

        $this->cerrarFormPregunta();
    }

    public function eliminarPregunta(int $preguntaId, PreguntaService $service): void
    {
        Gate::authorize('manage', $this->curso);
        abort_if($this->evaluacion->estaPublicada(), 403);

        $service->eliminar($this->evaluacion->preguntas()->findOrFail($preguntaId));
    }

    public function enviarIntento(IntentoEvaluacionService $service): void
    {
        $estudiante = Auth::user()->estudiante;

        abort_unless($estudiante !== null, 403);
        abort_unless($service->puedeRendir($this->evaluacion, $estudiante), 403);

        $respuestas = [];

        foreach ($this->evaluacion->preguntas as $pregunta) {
            $respuestas[$pregunta->id] = match ($pregunta->tipo) {
                TipoPreguntaEnum::OPCION_MULTIPLE => ['alternativas' => $this->respuestasMultiples[$pregunta->id] ?? []],
                TipoPreguntaEnum::OPCION_UNICA => ['alternativas' => ($this->respuestasUnicas[$pregunta->id] ?? '') !== '' ? [(int) $this->respuestasUnicas[$pregunta->id]] : []],
                TipoPreguntaEnum::PREGUNTA_ABIERTA => ['texto' => $this->respuestasTexto[$pregunta->id] ?? null],
            };
        }

        $service->enviar($this->evaluacion, $estudiante, $respuestas);
    }

    public function calificarAbierta(int $respuestaId, IntentoEvaluacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $respuesta = RespuestaEstudiante::query()
            ->whereHas('pregunta', fn ($query) => $query->where('evaluacion_id', $this->evaluacion->id))
            ->findOrFail($respuestaId);

        $puntajeMax = (float) $respuesta->pregunta->puntaje;

        $this->validate([
            "puntajesAbiertas.{$respuestaId}" => "required|numeric|min:0|max:{$puntajeMax}",
        ]);

        $service->calificarAbierta($respuesta, (float) $this->puntajesAbiertas[$respuestaId], Auth::id());
    }

    public function with(EvaluacionService $evaluaciones, IntentoEvaluacionService $intentos, BloqueoAccesoService $bloqueos): array
    {
        $puedeGestionar = Gate::allows('manage', $this->curso);
        $preguntas = $this->evaluacion->preguntas()->with('alternativas')->get();

        if ($puedeGestionar) {
            return [
                'puedeGestionar' => true,
                'puedePublicar' => Auth::user()->hasPermissionTo('evaluaciones.publicar'),
                'estudiantes' => $evaluaciones->estudiantesDelHorario($this->curso->horario),
                'preguntas' => $preguntas,
                'resultados' => $this->evaluacion->esVirtual() ? $intentos->resultadosDe($this->evaluacion) : collect(),
                'miIntento' => null,
                'misRespuestas' => collect(),
                'miCalificacion' => null,
                'puedeRendir' => false,
                'estaBloqueado' => false,
            ];
        }

        $estudiante = Auth::user()->estudiante;
        $estaBloqueado = $estudiante !== null
            && ($bloqueos->estaBloqueado($estudiante) || $bloqueos->tieneCuotasVencidasEnCicloActual($estudiante));

        $miCalificacion = null;
        $miIntento = null;
        $misRespuestas = collect();
        $puedeRendir = false;

        if ($estudiante && ! $estaBloqueado) {
            $miCalificacion = Calificacion::query()
                ->where('evaluacion_id', $this->evaluacion->id)
                ->where('estudiante_id', $estudiante->id)
                ->first();

            if ($this->evaluacion->esVirtual()) {
                $miIntento = $intentos->intentoDe($this->evaluacion, $estudiante);
                $puedeRendir = $intentos->puedeRendir($this->evaluacion, $estudiante);

                if ($miIntento) {
                    $misRespuestas = RespuestaEstudiante::query()
                        ->where('estudiante_id', $estudiante->id)
                        ->whereIn('pregunta_id', $preguntas->pluck('id'))
                        ->get()
                        ->keyBy('pregunta_id');
                }
            }
        }

        return [
            'puedeGestionar' => false,
            'puedePublicar' => false,
            'estudiantes' => collect(),
            'preguntas' => $preguntas,
            'resultados' => collect(),
            'miIntento' => $miIntento,
            'misRespuestas' => $misRespuestas,
            'miCalificacion' => $miCalificacion,
            'puedeRendir' => $puedeRendir,
            'estaBloqueado' => $estaBloqueado,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('aula-virtual.show', $curso) }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← {{ $curso->horario->curso->nombre }}</a>
        <div class="mt-1 flex items-center gap-2">
            <h1 class="font-display text-2xl text-ink">{{ $evaluacion->nombre }}</h1>
            <x-badge variant="info">{{ $evaluacion->tipo->label() }}</x-badge>
        </div>
        <p class="mt-1 text-sm text-ink-dim">{{ $evaluacion->fecha->format('d/m/Y') }}</p>
    </x-slot>

    @if ($puedeGestionar)
        <div class="mb-4 flex items-center justify-between">
            <span @class([
                'rounded-full px-2.5 py-1 text-xs font-medium',
                'bg-accent-soft text-accent' => $evaluacion->estaPublicada(),
                'bg-surface-2 text-ink-faint' => ! $evaluacion->estaPublicada(),
            ])>
                {{ $evaluacion->estado->label() }}
            </span>

            @if ($puedePublicar && ! $evaluacion->estaPublicada())
                <x-secondary-button
                    type="button"
                    :disabled="$evaluacion->esVirtual() && $preguntas->isEmpty()"
                    x-on:click="$store.confirm.preguntar('¿Publicar esta evaluación? {{ $evaluacion->esVirtual() ? 'Los estudiantes podrán rendirla y ya no podrás editar sus preguntas.' : 'Los estudiantes podrán ver sus notas.' }}', () => $wire.publicar(), { etiquetaConfirmar: 'Publicar' })"
                >
                    Publicar
                </x-secondary-button>
            @endif
        </div>

        @if ($evaluacion->esVirtual() && $preguntas->isEmpty() && ! $evaluacion->estaPublicada())
            <p class="mb-4 text-xs text-ink-faint">Agrega al menos una pregunta antes de publicar.</p>
        @endif

        @if ($guardado)
            <p class="mb-4 rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">Notas guardadas.</p>
        @endif

        {{-- Física: enlace externo + grilla de notas + importación --}}
        @if ($evaluacion->tipo->value === 'fisico')
            <div class="mb-4 space-y-3 rounded-2xl border border-border bg-surface shadow-sm p-4">
                @unless ($evaluacion->estaPublicada())
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
                </div>
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="actualizarEnlace">Guardar enlace</x-secondary-button>
                </div>
            </div>

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
                            >
                            <input
                                type="text"
                                wire:model="observaciones.{{ $estudiante->id }}"
                                placeholder="Observación (opcional)"
                                class="w-48 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                            >
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay estudiantes matriculados en este horario.</p>
                @endforelse
            </div>

            @if ($estudiantes->isNotEmpty())
                <div class="mt-4 flex justify-end">
                    <x-primary-button type="button" wire:click="guardarNotas">Guardar notas</x-primary-button>
                </div>
            @endif

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
        @else
            {{-- Virtual: banco de preguntas (borrador) o resultados (publicada) --}}
            @unless ($evaluacion->estaPublicada())
                <div class="mb-4 flex justify-end">
                    <x-secondary-button type="button" wire:click="abrirFormPregunta">+ Nueva pregunta</x-secondary-button>
                </div>

                @if ($mostrarFormPregunta)
                    <form wire:submit="guardarPregunta" class="mb-4 space-y-3 rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <div>
                            <x-input-label for="preguntaEnunciado" value="Enunciado" />
                            <textarea wire:model="preguntaEnunciado" id="preguntaEnunciado" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                            <x-input-error :messages="$errors->get('preguntaEnunciado')" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="preguntaTipo" value="Tipo" />
                                <x-select-input
                                    wire:model.live="preguntaTipo"
                                    id="preguntaTipo"
                                    class="mt-1 block w-full"
                                    :options="collect(TipoPreguntaEnum::cases())->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                                    placeholder="Selecciona…"
                                    :disabled="$preguntaEditandoId !== null"
                                />
                                <x-input-error :messages="$errors->get('preguntaTipo')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="preguntaPuntaje" value="Puntaje" />
                                <x-text-input wire:model="preguntaPuntaje" id="preguntaPuntaje" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('preguntaPuntaje')" class="mt-1" />
                            </div>
                        </div>

                        @if ($preguntaTipo === 'opcion_multiple' || $preguntaTipo === 'opcion_unica')
                            <div class="space-y-2">
                                <x-input-label value="Alternativas (marca la(s) correcta(s))" />
                                @foreach ($alternativasTexto as $indice => $texto)
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="alternativasCorrectas.{{ $indice }}" class="rounded border-border text-accent focus:ring-accent">
                                        <x-text-input wire:model="alternativasTexto.{{ $indice }}" class="block w-full" placeholder="Texto de la alternativa" />
                                        @if (count($alternativasTexto) > 2)
                                            <button type="button" wire:click="quitarAlternativa({{ $indice }})" class="shrink-0 text-xs text-danger hover:underline">Quitar</button>
                                        @endif
                                    </div>
                                @endforeach
                                <x-secondary-button type="button" wire:click="agregarAlternativa">+ Alternativa</x-secondary-button>
                                <x-input-error :messages="$errors->get('alternativas')" class="mt-1" />
                            </div>
                        @endif

                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" wire:click="cerrarFormPregunta">Cancelar</x-secondary-button>
                            <x-primary-button type="submit">Guardar</x-primary-button>
                        </div>
                    </form>
                @endif
            @endunless

            <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                @forelse ($preguntas as $indice => $pregunta)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-ink">{{ $indice + 1 }}. {{ $pregunta->enunciado }}</p>
                                <p class="mt-1 text-xs text-ink-faint">{{ $pregunta->tipo->label() }} · {{ number_format((float) $pregunta->puntaje, 2) }} pts</p>
                                @if ($pregunta->tipo->requiereAlternativas())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($pregunta->alternativas as $alternativa)
                                            <li class="flex items-center gap-1.5 text-xs {{ $alternativa->es_correcta ? 'font-medium text-ok' : 'text-ink-dim' }}">
                                                @if ($alternativa->es_correcta)
                                                    <x-heroicon-o-check class="h-3.5 w-3.5 shrink-0" />
                                                @endif
                                                {{ $alternativa->texto }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            @unless ($evaluacion->estaPublicada())
                                <div class="flex shrink-0 gap-2 text-xs">
                                    <button type="button" wire:click="abrirFormPregunta({{ $pregunta->id }})" class="font-medium text-accent hover:underline">Editar</button>
                                    <button type="button" x-on:click="$store.confirm.preguntar('¿Eliminar esta pregunta?', () => $wire.eliminarPregunta({{ $pregunta->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })" class="font-medium text-danger hover:underline">Eliminar</button>
                                </div>
                            @endunless
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay preguntas.</p>
                @endforelse
            </div>

            @if ($evaluacion->estaPublicada())
                <div class="mt-6">
                    <h3 class="mb-2 font-display text-sm text-ink">Resultados</h3>
                    <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                        @forelse ($estudiantes as $estudiante)
                            @php $intento = $resultados->get($estudiante->id); @endphp
                            <div class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-ink">{{ $estudiante->nombreCompleto() }}</p>
                                    @if (! $intento)
                                        <span class="rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-faint">Sin enviar</span>
                                    @elseif (! $intento->estaCalificado())
                                        <span class="rounded-full bg-warn/10 px-2 py-0.5 text-xs text-warn">Pendiente de revisión</span>
                                    @else
                                        <span class="rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">Calificado</span>
                                    @endif
                                </div>

                                @if ($intento && ! $intento->estaCalificado())
                                    @php
                                        $respuestasAbiertas = RespuestaEstudiante::query()
                                            ->where('estudiante_id', $estudiante->id)
                                            ->whereIn('pregunta_id', $preguntas->where('tipo', TipoPreguntaEnum::PREGUNTA_ABIERTA)->pluck('id'))
                                            ->whereNull('puntaje_obtenido')
                                            ->with('pregunta')
                                            ->get();
                                    @endphp
                                    @if ($respuestasAbiertas->isNotEmpty())
                                        <div class="mt-3 space-y-3 border-t border-border pt-3">
                                            @foreach ($respuestasAbiertas as $respuesta)
                                                <div>
                                                    <p class="text-xs text-ink-faint">{{ $respuesta->pregunta->enunciado }}</p>
                                                    <p class="mt-1 rounded-md bg-surface-2 px-3 py-2 text-sm text-ink">{{ $respuesta->texto_respuesta ?: '(sin respuesta)' }}</p>
                                                    <form wire:submit="calificarAbierta({{ $respuesta->id }})" class="mt-2 flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="{{ $respuesta->pregunta->puntaje }}"
                                                            wire:model="puntajesAbiertas.{{ $respuesta->id }}"
                                                            placeholder="0 – {{ $respuesta->pregunta->puntaje }}"
                                                            class="w-28 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"
                                                        >
                                                        <x-secondary-button type="submit">Calificar</x-secondary-button>
                                                    </form>
                                                    <x-input-error :messages="$errors->get('puntajesAbiertas.'.$respuesta->id)" class="mt-1" />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay estudiantes matriculados en este horario.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        @endif
    @elseif ($estaBloqueado)
        <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-6 text-sm text-danger">
            <p class="font-medium">Tus notas no están disponibles.</p>
            <p class="mt-1">Tienes cuotas vencidas sin pagar. Regulariza tu deuda en
                <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                o comunícate con Cobranza para un compromiso de pago.</p>
        </div>
    @elseif ($evaluacion->tipo->value === 'fisico')
        {{-- Estudiante, evaluación Física: solo ve su nota --}}
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            @if ($miCalificacion)
                <p class="text-sm text-ink">Tu nota:</p>
                <p class="mt-1 font-display text-3xl text-accent">{{ number_format((float) $miCalificacion->nota_numerica, 2) }}</p>
                <p class="text-xs text-ink-faint">{{ $miCalificacion->notaLetra()->value }}</p>
                @if ($miCalificacion->observaciones)
                    <p class="mt-3 text-sm text-ink-dim">{{ $miCalificacion->observaciones }}</p>
                @endif
            @else
                <p class="text-sm text-ink-faint">Todavía no tienes una nota registrada para esta evaluación.</p>
            @endif

            @if ($evaluacion->enlaceDisponible())
                <p class="mt-4 border-t border-border pt-4 text-sm text-ink-dim">
                    Enlace para rendirla:
                    <a href="{{ $evaluacion->enlace_externo }}" target="_blank" class="font-medium text-accent hover:underline">Abrir enlace →</a>
                    @if ($evaluacion->disponible_hasta)
                        <span class="block text-xs text-ink-faint">Disponible hasta {{ $evaluacion->disponible_hasta->format('d/m/Y H:i') }}</span>
                    @endif
                </p>
            @endif
        </div>
    @else
        {{-- Estudiante, evaluación Virtual --}}
        @if (! $evaluacion->estaPublicada())
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">Esta evaluación todavía no está disponible.</p>
        @elseif ($miIntento && $miIntento->estaCalificado())
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <p class="text-sm text-ink">Tu nota:</p>
                <p class="mt-1 font-display text-3xl text-accent">{{ number_format((float) $miCalificacion->nota_numerica, 2) }}</p>
                <p class="text-xs text-ink-faint">{{ $miCalificacion->notaLetra()->value }}</p>
            </div>

            <div class="mt-4 divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                @foreach ($preguntas as $indice => $pregunta)
                    @php $miRespuesta = $misRespuestas->get($pregunta->id); @endphp
                    <div class="px-4 py-3 text-sm">
                        <p class="text-ink">{{ $indice + 1 }}. {{ $pregunta->enunciado }}</p>
                        <p class="mt-1 text-xs text-ink-faint">{{ number_format((float) ($miRespuesta?->puntaje_obtenido ?? 0), 2) }} / {{ number_format((float) $pregunta->puntaje, 2) }} pts</p>

                        @if ($pregunta->tipo->requiereAlternativas())
                            <ul class="mt-2 space-y-1">
                                @foreach ($pregunta->alternativas as $alternativa)
                                    @php $fueElegida = in_array($alternativa->id, $miRespuesta?->alternativas_elegidas ?? [], true); @endphp
                                    <li @class([
                                        'flex items-center gap-1.5 text-xs',
                                        'font-medium text-ok' => $alternativa->es_correcta,
                                        'font-medium text-danger' => ! $alternativa->es_correcta && $fueElegida,
                                        'text-ink-dim' => ! $alternativa->es_correcta && ! $fueElegida,
                                    ])>
                                        @if ($fueElegida)
                                            <x-heroicon-o-check class="h-3.5 w-3.5 shrink-0" />
                                        @endif
                                        {{ $alternativa->texto }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 rounded-md bg-surface-2 px-3 py-2 text-xs text-ink">{{ $miRespuesta?->texto_respuesta ?: '(sin respuesta)' }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif ($miIntento)
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">Ya enviaste tus respuestas. Están en revisión.</p>
        @elseif ($puedeRendir)
            <form wire:submit="enviarIntento" class="space-y-4">
                @foreach ($preguntas as $indice => $pregunta)
                    <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <p class="text-sm text-ink">{{ $indice + 1 }}. {{ $pregunta->enunciado }}</p>
                        <p class="mt-1 text-xs text-ink-faint">{{ number_format((float) $pregunta->puntaje, 2) }} pts</p>

                        @if ($pregunta->tipo->value === 'opcion_multiple')
                            <div class="mt-3 space-y-2">
                                @foreach ($pregunta->alternativas as $alternativa)
                                    <label class="flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" wire:model="respuestasMultiples.{{ $pregunta->id }}" value="{{ $alternativa->id }}" class="rounded border-border text-accent focus:ring-accent">
                                        {{ $alternativa->texto }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($pregunta->tipo->value === 'opcion_unica')
                            <div class="mt-3 space-y-2">
                                @foreach ($pregunta->alternativas as $alternativa)
                                    <label class="flex items-center gap-2 text-sm text-ink">
                                        <input type="radio" wire:model="respuestasUnicas.{{ $pregunta->id }}" value="{{ $alternativa->id }}" class="border-border text-accent focus:ring-accent">
                                        {{ $alternativa->texto }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <textarea wire:model="respuestasTexto.{{ $pregunta->id }}" rows="3" class="mt-3 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                        @endif
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <x-primary-button type="button" x-on:click="$store.confirm.preguntar('¿Enviar tus respuestas? No podrás modificarlas después.', () => $wire.enviarIntento(), { etiquetaConfirmar: 'Enviar' })">
                        Enviar
                    </x-primary-button>
                </div>
            </form>
        @else
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">Esta evaluación ya no está disponible para rendir.</p>
        @endif
    @endif
</div>
