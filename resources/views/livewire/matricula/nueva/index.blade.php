<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\CursoService;
use App\Modules\Academico\Services\SeccionService;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\ModalidadEstudioEnum;
use App\Modules\Matricula\Enums\TipoMatriculaEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * El módulo de Matrícula: a diferencia de "Estudiantes" (solo registro,
 * ver matricula/registrar.blade.php), esta pantalla sirve exclusivamente
 * para matricular a un estudiante YA registrado -- a una carrera, a un
 * curso corto, o a ambos a la vez -- eligiendo semestre y periodo
 * académico libremente (sin auto-resolución).
 */
new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $estudianteId = '';

    public string $terminoBusqueda = '';

    public string $estudianteNombre = '';

    public int $paso = 1;

    public string $tipoMatricula = 'instituto';

    public string $claseMatricula = 'regular';

    public string $modalidadEstudio = 'presencial';

    public string $seccionAcademicaId = '';

    public string $cicloId = '';

    public string $gradoId = '';

    /**
     * @var list<string>
     */
    public array $cursosEspecificosIds = [];

    public string $fechaMatricula = '';

    public string $observacionesMatricula = '';

    /**
     * @var list<string>
     */
    public array $cursosCortoIds = [''];

    public bool $configurarCronograma = false;

    public string $numeroCuotasCronograma = '6';

    public string $montoTotalCronograma = '';

    /** @var array<int, string> */
    public array $cuotaMontos = [];

    /** @var array<int, string> */
    public array $cuotaFechas = [];

    /** @var array<int, array{concepto: string, monto: string}> */
    public array $cargosAdicionales = [];

    public ?string $modalidadRestringida = null;

    public function mount(): void
    {
        Gate::authorize('matricula.crear');

        $this->fechaMatricula = now()->format('Y-m-d');
        $this->modalidadRestringida = auth()->user()->modalidadMatriculaRestringida();

        if ($this->modalidadRestringida !== null) {
            $this->tipoMatricula = $this->modalidadRestringida;
        }

        if ($this->estudianteId !== '') {
            $estudiante = Estudiante::query()->find($this->estudianteId);
            $this->estudianteNombre = $estudiante?->nombreCompleto() ?? '';

            if ($estudiante === null) {
                $this->estudianteId = '';
            }
        }
    }

    #[Computed]
    public function cursosDelSemestre(): Collection
    {
        if ($this->cicloId === '' || $this->gradoId === '') {
            return collect();
        }

        return Horario::query()
            ->where('grado_id', (int) $this->gradoId)
            ->where('ciclo_id', (int) $this->cicloId)
            ->with('curso')
            ->get()
            ->pluck('curso')
            ->unique('id')
            ->sortBy('nombre')
            ->values();
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->estudianteId = (string) $estudianteId;
        $this->estudianteNombre = $nombre;
        $this->terminoBusqueda = '';
    }

    public function cambiarEstudiante(): void
    {
        $this->reset([
            'estudianteId', 'estudianteNombre', 'paso', 'tipoMatricula', 'claseMatricula',
            'modalidadEstudio', 'seccionAcademicaId', 'cicloId', 'gradoId', 'cursosEspecificosIds',
            'observacionesMatricula', 'cursosCortoIds', 'configurarCronograma', 'numeroCuotasCronograma',
            'montoTotalCronograma', 'cuotaMontos', 'cuotaFechas', 'cargosAdicionales',
        ]);
        $this->fechaMatricula = now()->format('Y-m-d');

        if ($this->modalidadRestringida !== null) {
            $this->tipoMatricula = $this->modalidadRestringida;
        }
    }

    public function updatedGradoId(): void
    {
        $this->cursosEspecificosIds = [];
    }

    public function avanzar(): void
    {
        if ($this->modalidadRestringida !== null) {
            $this->tipoMatricula = $this->modalidadRestringida;
        }

        if ($this->tipoMatricula === 'curso_corto') {
            $this->validate([
                'cursosCortoIds' => 'required|array|min:1',
                'cursosCortoIds.*' => 'required|integer|exists:cursos,id|distinct',
                'fechaMatricula' => 'required|date',
            ]);
        } else {
            $this->validate([
                'cicloId' => 'required|integer|exists:ciclos,id',
                'gradoId' => 'required|integer|exists:grados,id',
                'fechaMatricula' => 'required|date',
                'claseMatricula' => 'required|string|in:'.implode(',', array_column(TipoMatriculaEnum::cases(), 'value')),
                'modalidadEstudio' => 'required|string|in:'.implode(',', array_column(ModalidadEstudioEnum::cases(), 'value')),
                'seccionAcademicaId' => 'nullable|integer|exists:secciones_academicas,id',
            ]);
        }

        $this->paso = 2;
    }

    public function retroceder(): void
    {
        $this->paso = 1;
    }

    public function generarCronogramaAutomatico(): void
    {
        $this->validate([
            'numeroCuotasCronograma' => ['required', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
            'montoTotalCronograma' => 'required|numeric|min:1',
        ]);

        $numero = (int) $this->numeroCuotasCronograma;
        $total = (float) $this->montoTotalCronograma;
        $montoPorCuota = round($total / $numero, 2);
        $montoAcumulado = 0.0;

        $this->cuotaMontos = [];
        $this->cuotaFechas = [];

        for ($i = 1; $i <= $numero; $i++) {
            $esUltima = $i === $numero;
            $monto = $esUltima ? round($total - $montoAcumulado, 2) : $montoPorCuota;
            $montoAcumulado += $monto;

            $this->cuotaMontos[$i] = number_format($monto, 2, '.', '');
            $this->cuotaFechas[$i] = now()->addMonths($i)->format('Y-m-d');
        }
    }

    public function agregarCargoAdicional(): void
    {
        $this->cargosAdicionales[] = ['concepto' => '', 'monto' => '', 'fecha_vencimiento' => ''];
    }

    public function quitarCargoAdicional(int $indice): void
    {
        unset($this->cargosAdicionales[$indice]);
        $this->cargosAdicionales = array_values($this->cargosAdicionales);
    }

    public function agregarCursoCorto(): void
    {
        $this->cursosCortoIds[] = '';
    }

    public function quitarCursoCorto(int $indice): void
    {
        if (count($this->cursosCortoIds) <= 1) {
            return;
        }

        unset($this->cursosCortoIds[$indice]);
        $this->cursosCortoIds = array_values($this->cursosCortoIds);
    }

    public function confirmar(MatriculaService $matriculaService, PlanPagoService $planPagoService): void
    {
        Gate::authorize('matricula.crear');

        if ($this->modalidadRestringida !== null) {
            $this->tipoMatricula = $this->modalidadRestringida;
        }

        if ($this->configurarCronograma) {
            $this->validate([
                'numeroCuotasCronograma' => ['required', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
                'cuotaMontos' => 'required|array|min:1',
                'cuotaMontos.*' => 'required|numeric|min:0.01',
                'cuotaFechas' => 'required|array|min:1',
                'cuotaFechas.*' => 'required|date',
            ]);

            if (count($this->cuotaMontos) !== (int) $this->numeroCuotasCronograma) {
                $this->addError('numeroCuotasCronograma', 'Genera el cronograma automático antes de confirmar la matrícula.');

                return;
            }
        }

        foreach ($this->cargosAdicionales as $indice => $cargo) {
            $tieneConcepto = trim($cargo['concepto']) !== '';
            $tieneMonto = $cargo['monto'] !== '';

            if ($tieneConcepto !== $tieneMonto) {
                $this->addError("cargosAdicionales.{$indice}.concepto", 'Completa el concepto y el monto de este cargo, o quítalo.');

                return;
            }

            if (($cargo['fecha_vencimiento'] ?? '') !== '' && ! strtotime($cargo['fecha_vencimiento'])) {
                $this->addError("cargosAdicionales.{$indice}.fecha_vencimiento", 'Fecha inválida.');

                return;
            }
        }

        $estudiante = Estudiante::query()->findOrFail($this->estudianteId);

        if ($this->tipoMatricula === 'curso_corto') {
            foreach ($this->cursosCortoIds as $cursoCortoId) {
                $matriculaService->matricularCursoCorto($estudiante, [
                    'curso_id' => (int) $cursoCortoId,
                    'fecha_inicio' => $this->fechaMatricula,
                    'observaciones' => $this->observacionesMatricula ?: null,
                    'registrado_por' => auth()->id(),
                ]);
            }
        } else {
            $matricula = $matriculaService->matricular($estudiante, new RegistrarMatriculaData(
                cicloId: (int) $this->cicloId,
                gradoId: (int) $this->gradoId,
                observaciones: $this->observacionesMatricula ?: null,
                registradoPor: auth()->id(),
                fechaMatricula: $this->fechaMatricula !== '' ? $this->fechaMatricula : null,
                cursoIds: $this->cursosEspecificosIds !== [] ? array_map('intval', $this->cursosEspecificosIds) : null,
                tipoMatricula: TipoMatriculaEnum::from($this->claseMatricula),
                modalidadEstudio: ModalidadEstudioEnum::from($this->modalidadEstudio),
                seccionAcademicaId: $this->seccionAcademicaId !== '' ? (int) $this->seccionAcademicaId : null,
            ));

            $this->crearCronogramaSiCorresponde($matricula, $planPagoService);
        }

        $this->crearCargosAdicionalesSiCorresponde($estudiante);

        session()->flash('status', "Matrícula de {$estudiante->nombreCompleto()} registrada correctamente.");
        $this->cambiarEstudiante();
    }

    private function crearCronogramaSiCorresponde(Matricula $matricula, PlanPagoService $planPagoService): void
    {
        if (! $this->configurarCronograma) {
            return;
        }

        $cuotas = collect($this->cuotaMontos)
            ->map(fn ($monto, $numero) => [
                'monto' => (float) $monto,
                'fecha_vencimiento' => $this->cuotaFechas[$numero],
            ])
            ->values()
            ->all();

        $planPagoService->crear(
            $matricula,
            NumeroCuotasEnum::from((int) $this->numeroCuotasCronograma),
            array_sum(array_column($cuotas, 'monto')),
            $cuotas,
        );
    }

    private function crearCargosAdicionalesSiCorresponde(Estudiante $estudiante): void
    {
        foreach ($this->cargosAdicionales as $cargo) {
            if (trim($cargo['concepto']) === '' || $cargo['monto'] === '') {
                continue;
            }

            CargoAdicional::query()->create([
                'estudiante_id' => $estudiante->id,
                'concepto' => $cargo['concepto'],
                'monto' => (float) $cargo['monto'],
                'fecha_vencimiento' => ($cargo['fecha_vencimiento'] ?? '') ?: null,
                'estado' => EstadoCuotaEnum::PENDIENTE,
                'registrado_por' => auth()->id(),
            ]);
        }
    }

    public function with(CursoService $cursos, SeccionService $secciones): array
    {
        $resultadosBusqueda = collect();

        if ($this->terminoBusqueda !== '') {
            $termino = $this->terminoBusqueda;
            $resultadosBusqueda = Estudiante::query()
                ->where(function ($query) use ($termino) {
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $grados = Grado::query()->where('activo', true)->with('programaEstudio')->orderBy('orden')->get();

        return [
            'resultadosBusqueda' => $resultadosBusqueda,
            'gradosCompatibles' => $grados,
            'ciclosDisponibles' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'numerosCuotas' => NumeroCuotasEnum::cases(),
            'cursosCortosDisponibles' => $cursos->deCapacitacion(),
            'tiposMatricula' => TipoMatriculaEnum::cases(),
            'modalidadesEstudio' => ModalidadEstudioEnum::cases(),
            'seccionesAcademicas' => $secciones->activas(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Matrícula</h1>
        <p class="mt-1 text-sm text-ink-dim">Matricula a un estudiante ya registrado en una carrera y/o un curso corto, eligiendo semestre y periodo académico libremente.</p>
    </x-slot>

    @can('matricula.crear')
        <div class="mb-4 flex justify-end">
            <a href="{{ route('matriculas.carga-masiva') }}" wire:navigate class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-4 py-2 font-display text-sm font-medium text-ink transition hover:bg-surface-2">
                <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                Matrícula masiva
            </a>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="max-w-3xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
        <div>
            <x-input-label value="Estudiante" />
            @if ($estudianteId !== '')
                <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                    {{ $estudianteNombre }}
                    <button type="button" wire:click="cambiarEstudiante" class="text-xs underline">Cambiar</button>
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
                @elseif ($terminoBusqueda !== '')
                    <p class="mt-1 text-xs text-ink-faint">Sin resultados. Si el estudiante todavía no existe, regístralo primero desde Estudiantes.</p>
                @endif
            @endif
        </div>

        @if ($estudianteId !== '')
            <div class="mb-2 flex gap-1">
                @for ($i = 1; $i <= 2; $i++)
                    <div @class(['h-1.5 flex-1 rounded-full', 'bg-accent' => $i <= $paso, 'bg-surface-2' => $i > $paso])></div>
                @endfor
            </div>

            {{-- Paso 1: Matrícula --}}
            @if ($paso === 1)
                @unless ($modalidadRestringida !== null)
                    <div>
                        <x-input-label for="tipoMatricula" value="Tipo de matrícula" />
                        <x-select-input
                            wire:model.live="tipoMatricula"
                            id="tipoMatricula"
                            class="mt-1 block w-full sm:w-1/2"
                            :options="['instituto' => 'Instituto', 'curso_corto' => 'Curso corto']"
                        />
                        <p class="mt-1 text-xs text-ink-faint">Curso corto: el estudiante solo lleva un curso de capacitación individual, sin programa de estudio ni semestre. Puede combinarse con una matrícula de Instituto por separado.</p>
                    </div>
                @endunless
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @if ($tipoMatricula === 'instituto')
                        <div wire:key="tipo-instituto-ciclo">
                            <x-input-label for="cicloId" value="Periodo académico" />
                            <x-select-input
                                wire:model.live="cicloId"
                                id="cicloId"
                                class="mt-1 block w-full"
                                placeholder="Selecciona…"
                                :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                            />
                            @if ($ciclosDisponibles->isEmpty())
                                <p class="mt-1 text-xs text-danger">No hay ningún periodo académico registrado todavía. Créalo primero en Periodo Académico.</p>
                            @endif
                            <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                        </div>
                        <div wire:key="tipo-instituto-grado">
                            <x-input-label for="gradoId" value="Programa de estudio y semestre" />
                            <x-select-input
                                wire:model.live="gradoId"
                                id="gradoId"
                                class="mt-1 block w-full"
                                placeholder="Selecciona…"
                                :options="collect($gradosCompatibles)->mapWithKeys(fn ($grado) => [$grado->id => $grado->programaEstudio->nombre.' — '.$grado->nombre])"
                            />
                            <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                        </div>

                        @if ($gradoId !== '' && $this->cursosDelSemestre->isNotEmpty())
                            <div class="sm:col-span-2" wire:key="tipo-instituto-cursos-especificos">
                                <x-input-label value="Cursos específicos (opcional)" />
                                <p class="mt-1 text-xs text-ink-faint">Déjalo sin marcar para matricular en todos los cursos del semestre. Marca solo algunos para matricular únicamente en esos.</p>
                                <div class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-md border border-border bg-surface p-2">
                                    @foreach ($this->cursosDelSemestre as $cursoDisponible)
                                        <label class="flex items-center gap-2 text-sm text-ink">
                                            <input
                                                type="checkbox"
                                                value="{{ $cursoDisponible->id }}"
                                                wire:model="cursosEspecificosIds"
                                                class="rounded border-border text-accent focus:ring-accent"
                                            >
                                            {{ $cursoDisponible->nombre }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div wire:key="tipo-instituto-clase">
                            <x-input-label for="claseMatricula" value="Tipo de matrícula" />
                            <x-select-input
                                wire:model="claseMatricula"
                                id="claseMatricula"
                                class="mt-1 block w-full"
                                :options="collect($tiposMatricula)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                            />
                            <p class="mt-1 text-xs text-ink-faint">Convalidación: estudiante con estudios truncos que retoma directamente en este semestre.</p>
                            <x-input-error :messages="$errors->get('claseMatricula')" class="mt-1" />
                        </div>
                        <div wire:key="tipo-instituto-modalidad-estudio">
                            <x-input-label for="modalidadEstudio" value="Modalidad de estudio" />
                            <x-select-input
                                wire:model="modalidadEstudio"
                                id="modalidadEstudio"
                                class="mt-1 block w-full"
                                placeholder="Selecciona…"
                                :options="collect($modalidadesEstudio)->mapWithKeys(fn ($modalidad) => [$modalidad->value => $modalidad->label()])"
                            />
                            <x-input-error :messages="$errors->get('modalidadEstudio')" class="mt-1" />
                        </div>
                        <div wire:key="tipo-instituto-seccion">
                            <x-input-label for="seccionAcademicaId" value="Sección (opcional)" />
                            <x-select-input
                                wire:model="seccionAcademicaId"
                                id="seccionAcademicaId"
                                class="mt-1 block w-full"
                                placeholder="Sin sección"
                                :options="collect($seccionesAcademicas)->mapWithKeys(fn ($seccion) => [$seccion->id => $seccion->letra])"
                            />
                            <x-input-error :messages="$errors->get('seccionAcademicaId')" class="mt-1" />
                        </div>
                    @else
                        <div class="sm:col-span-2" wire:key="tipo-curso-corto-cursos">
                            <x-input-label value="Cursos" />
                            <div class="mt-1 space-y-2">
                                @foreach ($cursosCortoIds as $indice => $cursoCortoId)
                                    <div class="flex items-start gap-2" wire:key="curso-corto-{{ $indice }}">
                                        <div class="flex-1">
                                            <x-select-input
                                                wire:model="cursosCortoIds.{{ $indice }}"
                                                id="cursosCortoIds-{{ $indice }}"
                                                placeholder="Selecciona un curso…"
                                                class="block w-full"
                                                :options="collect($cursosCortosDisponibles)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre])"
                                            />
                                            <x-input-error :messages="$errors->get('cursosCortoIds.'.$indice)" class="mt-1" />
                                        </div>
                                        @if (count($cursosCortoIds) > 1)
                                            <button type="button" wire:click="quitarCursoCorto({{ $indice }})" wire:loading.attr="disabled" class="mt-2 text-xs text-danger hover:underline disabled:cursor-not-allowed disabled:opacity-50">Quitar</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('cursosCortoIds')" class="mt-1" />
                            <button type="button" wire:click="agregarCursoCorto" wire:loading.attr="disabled" class="mt-2 text-xs font-medium text-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50">+ Agregar curso</button>
                        </div>
                    @endif
                    <div>
                        <x-input-label for="fechaMatricula" :value="$tipoMatricula === 'instituto' ? 'Fecha de matrícula' : 'Fecha de inicio'" />
                        <x-date-input wire:model="fechaMatricula" id="fechaMatricula" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaMatricula')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="observacionesMatricula" value="Observaciones (opcional)" />
                        <textarea wire:model="observacionesMatricula" id="observacionesMatricula" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-primary-button type="button" wire:click="avanzar">Continuar</x-primary-button>
                </div>
            @endif

            {{-- Paso 2: Cronograma y cargos --}}
            @if ($paso === 2)
                @if ($tipoMatricula !== 'curso_corto')
                    <h2 class="font-display text-lg text-ink">Cronograma de pagos</h2>
                    <label class="mt-4 flex items-center gap-2 text-sm text-ink-dim">
                        <input type="checkbox" wire:model.live="configurarCronograma" class="rounded border-border text-accent focus:ring-accent">
                        Configurar el plan de pagos ahora
                    </label>
                    <p class="mt-1 text-xs text-ink-faint">Si lo dejas sin marcar, se puede armar después desde Pagos y Cobranza.</p>

                    @if ($configurarCronograma)
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="numeroCuotasCronograma" value="Número de cuotas" />
                                <x-select-input
                                    wire:model="numeroCuotasCronograma"
                                    id="numeroCuotasCronograma"
                                    class="mt-1 block w-full"
                                    :options="collect($numerosCuotas)->mapWithKeys(fn ($opcion) => [(string) $opcion->value => $opcion->label()])"
                                />
                                <x-input-error :messages="$errors->get('numeroCuotasCronograma')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="montoTotalCronograma" value="Monto total (S/)" />
                                <x-text-input wire:model="montoTotalCronograma" id="montoTotalCronograma" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('montoTotalCronograma')" class="mt-1" />
                            </div>
                            <div class="flex items-end">
                                <x-secondary-button type="button" wire:click="generarCronogramaAutomatico" class="w-full justify-center">
                                    Generar cronograma
                                </x-secondary-button>
                            </div>
                        </div>

                        @if (count($cuotaMontos) > 0)
                            <div class="mt-4 overflow-hidden rounded-lg border border-border">
                                <table class="min-w-full divide-y divide-border text-sm">
                                    <thead class="bg-surface-2">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Cuota</th>
                                            <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto (S/)</th>
                                            <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Vencimiento</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach ($cuotaMontos as $numero => $monto)
                                            <tr wire:key="cuota-{{ $numero }}">
                                                <td class="px-4 py-2 text-ink-dim">{{ $numero }}</td>
                                                <td class="px-4 py-2">
                                                    <input type="number" step="0.01" min="0" wire:model="cuotaMontos.{{ $numero }}" class="w-28 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                                </td>
                                                <td class="px-4 py-2">
                                                    <x-date-input wire:model="cuotaFechas.{{ $numero }}" class="w-40" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-ink-faint">Total del cronograma: S/ {{ number_format(array_sum(array_map('floatval', $cuotaMontos)), 2) }}</p>
                            <x-input-error :messages="$errors->get('cuotaMontos')" class="mt-1" />
                            <x-input-error :messages="$errors->get('cuotaMontos.*')" class="mt-1" />
                            <x-input-error :messages="$errors->get('cuotaFechas.*')" class="mt-1" />
                        @endif
                    @endif
                @endif

                <div class="mt-8 border-t border-border pt-6">
                    <h3 class="font-display text-base text-ink">Cargos adicionales (opcional)</h3>
                    <p class="mt-1 text-xs text-ink-faint">Otros cobros futuros propios de este estudiante — Convalidación, Exoneración, Recuperación, Visación, etc. Concepto y monto libres, editables aquí mismo.</p>

                    @if (count($cargosAdicionales) > 0)
                        <div class="mt-4 overflow-hidden rounded-lg border border-border">
                            <table class="min-w-full divide-y divide-border text-sm">
                                <thead class="bg-surface-2">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Concepto</th>
                                        <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto (S/)</th>
                                        <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Vence (opcional)</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($cargosAdicionales as $indice => $cargo)
                                        <tr wire:key="cargo-adicional-{{ $indice }}">
                                            <td class="px-4 py-2">
                                                <x-text-input wire:model="cargosAdicionales.{{ $indice }}.concepto" type="text" placeholder="Ej. Convalidación" class="w-full" />
                                                <x-input-error :messages="$errors->get('cargosAdicionales.'.$indice.'.concepto')" class="mt-1" />
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" step="0.01" min="0" wire:model="cargosAdicionales.{{ $indice }}.monto" class="w-28 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="date" wire:model="cargosAdicionales.{{ $indice }}.fecha_vencimiento" class="rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <button type="button" wire:click="quitarCargoAdicional({{ $indice }})" wire:loading.attr="disabled" class="text-xs text-danger hover:underline disabled:cursor-not-allowed disabled:opacity-50">Quitar</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <button type="button" wire:click="agregarCargoAdicional" wire:loading.attr="disabled" class="mt-3 text-xs font-medium text-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50">+ Agregar cargo</button>
                </div>

                <div class="mt-6 flex justify-between border-t border-border pt-4">
                    <x-secondary-button type="button" wire:click="retroceder">Atrás</x-secondary-button>
                    <x-primary-button type="button" wire:click="confirmar">Confirmar matrícula</x-primary-button>
                </div>
            @endif
        @endif
    </div>
</div>
