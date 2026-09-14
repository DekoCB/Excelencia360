<?php

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Modal "Ver" de matricula/index.blade.php, aislado en su propio componente
 * para que abrirlo no dispare una re-consulta de la lista paginada completa
 * (con eso el modal se anima al instante y solo carga los datos del
 * estudiante seleccionado, no toda la tabla).
 */
new class extends Component
{
    public ?int $estudianteId = null;

    public string $observacionesTexto = '';

    public ?int $editandoFechaFinMatriculaId = null;

    public string $fechaFinEstudioNueva = '';

    public ?int $editandoHorarioMatriculaId = null;

    public ?int $editandoHorarioCursoId = null;

    public string $horarioSeleccionado = '';

    public ?int $editandoMontoPlanId = null;

    public string $montoTotalNuevo = '';

    public ?int $editandoMontoCargoId = null;

    public string $montoCargoNuevo = '';

    public bool $agregandoCargo = false;

    public string $cargoConceptoNuevo = '';

    public string $cargoMontoNuevo = '';

    #[On('ver-estudiante')]
    public function abrir(int $estudianteId): void
    {
        Gate::authorize('matricula.ver');

        $this->estudianteId = $estudianteId;
        $this->observacionesTexto = Estudiante::query()->find($estudianteId)?->observaciones ?? '';
        $this->editandoFechaFinMatriculaId = null;
        $this->fechaFinEstudioNueva = '';
        $this->editandoHorarioMatriculaId = null;
        $this->editandoHorarioCursoId = null;
        $this->horarioSeleccionado = '';
        $this->editandoMontoPlanId = null;
        $this->montoTotalNuevo = '';
        $this->editandoMontoCargoId = null;
        $this->montoCargoNuevo = '';
        $this->agregandoCargo = false;
        $this->cargoConceptoNuevo = '';
        $this->cargoMontoNuevo = '';
    }

    public function verificarDocumento(int $documentoId, DocumentoEstudianteService $service): void
    {
        Gate::authorize('matricula.editar');

        $documento = DocumentoEstudiante::query()->findOrFail($documentoId);
        $service->verificar($documento);
    }

    /**
     * No se puede usar Pdf::loadView(...)->download() aquí: eso devuelve un
     * Illuminate\Http\Response plano, que Livewire no reconoce como
     * descarga de archivo (ver el mismo fix en reportes/index.blade.php y
     * historial-estudiante/index.blade.php). streamDownload() sí.
     */
    public function descargarDniPdf(int $documentoId, DocumentoEstudianteService $service)
    {
        Gate::authorize('matricula.ver');

        $documento = DocumentoEstudiante::query()->with('estudiante')->findOrFail($documentoId);

        return response()->streamDownload(
            fn () => print ($service->generarPdfDni($documento)->output()),
            "dni-{$documento->tipo->value}-{$documento->estudiante_id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function guardarObservaciones(): void
    {
        Gate::authorize('matricula.editar');

        Estudiante::query()->findOrFail($this->estudianteId)->update(['observaciones' => $this->observacionesTexto ?: null]);
    }

    public function editarFechaFinEstudio(int $matriculaId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $this->editandoFechaFinMatriculaId = $matriculaId;
        $this->fechaFinEstudioNueva = $matricula->fecha_fin_estudio?->format('Y-m-d') ?? '';
    }

    public function cancelarEdicionFechaFinEstudio(): void
    {
        $this->editandoFechaFinMatriculaId = null;
        $this->fechaFinEstudioNueva = '';
    }

    public function guardarFechaFinEstudio(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoFechaFinMatriculaId === null) {
            return;
        }

        $this->validate(['fechaFinEstudioNueva' => 'required|date']);

        $matricula = Matricula::query()->findOrFail($this->editandoFechaFinMatriculaId);

        $service->reasignarFechaFinEstudio($matricula, $this->fechaFinEstudioNueva);

        $this->editandoFechaFinMatriculaId = null;
        $this->fechaFinEstudioNueva = '';
    }

    public function editarHorario(int $matriculaId, int $cursoId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);
        $asignado = $matricula->horarios()->where('curso_id', $cursoId)->first();

        $this->editandoHorarioMatriculaId = $matriculaId;
        $this->editandoHorarioCursoId = $cursoId;
        $this->horarioSeleccionado = $asignado !== null ? (string) $asignado->id : '';
    }

    public function cancelarEdicionHorario(): void
    {
        $this->editandoHorarioMatriculaId = null;
        $this->editandoHorarioCursoId = null;
        $this->horarioSeleccionado = '';
    }

    public function guardarHorario(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoHorarioMatriculaId === null || $this->editandoHorarioCursoId === null) {
            return;
        }

        $matricula = Matricula::query()->findOrFail($this->editandoHorarioMatriculaId);

        $service->asignarHorarioDeCurso(
            $matricula,
            $this->editandoHorarioCursoId,
            $this->horarioSeleccionado !== '' ? (int) $this->horarioSeleccionado : null,
        );

        $this->editandoHorarioMatriculaId = null;
        $this->editandoHorarioCursoId = null;
        $this->horarioSeleccionado = '';
    }

    public function editarMontoPlan(int $planId): void
    {
        Gate::authorize('pagos.gestionar');

        $plan = PlanPago::query()->findOrFail($planId);

        $this->editandoMontoPlanId = $planId;
        $this->montoTotalNuevo = (string) $plan->monto_total;
    }

    public function cancelarEdicionMontoPlan(): void
    {
        $this->editandoMontoPlanId = null;
        $this->montoTotalNuevo = '';
    }

    public function guardarMontoPlan(PlanPagoService $service): void
    {
        Gate::authorize('pagos.gestionar');

        if ($this->editandoMontoPlanId === null) {
            return;
        }

        $this->validate(['montoTotalNuevo' => 'required|numeric|min:0.01']);

        $plan = PlanPago::query()->findOrFail($this->editandoMontoPlanId);

        $service->editarMontoTotal($plan, (float) $this->montoTotalNuevo);

        $this->editandoMontoPlanId = null;
        $this->montoTotalNuevo = '';
    }

    public function editarMontoCargo(int $cargoId): void
    {
        Gate::authorize('pagos.gestionar');

        $cargo = CargoAdicional::query()->findOrFail($cargoId);

        $this->editandoMontoCargoId = $cargoId;
        $this->montoCargoNuevo = (string) $cargo->monto;
    }

    public function cancelarEdicionMontoCargo(): void
    {
        $this->editandoMontoCargoId = null;
        $this->montoCargoNuevo = '';
    }

    public function guardarMontoCargo(PagoService $service): void
    {
        Gate::authorize('pagos.gestionar');

        if ($this->editandoMontoCargoId === null) {
            return;
        }

        $this->validate(['montoCargoNuevo' => 'required|numeric|min:0.01']);

        $cargo = CargoAdicional::query()->findOrFail($this->editandoMontoCargoId);

        $service->editarMontoCargoAdicional($cargo, (float) $this->montoCargoNuevo);

        $this->editandoMontoCargoId = null;
        $this->montoCargoNuevo = '';
    }

    public function mostrarFormularioCargo(): void
    {
        Gate::authorize('pagos.gestionar');

        $this->agregandoCargo = true;
    }

    public function cancelarNuevoCargo(): void
    {
        $this->agregandoCargo = false;
        $this->cargoConceptoNuevo = '';
        $this->cargoMontoNuevo = '';
    }

    public function guardarNuevoCargo(): void
    {
        Gate::authorize('pagos.gestionar');

        if ($this->estudianteId === null) {
            return;
        }

        $this->validate([
            'cargoConceptoNuevo' => 'required|string|max:100',
            'cargoMontoNuevo' => 'required|numeric|min:0.01',
        ]);

        CargoAdicional::query()->create([
            'estudiante_id' => $this->estudianteId,
            'concepto' => $this->cargoConceptoNuevo,
            'monto' => (float) $this->cargoMontoNuevo,
            'estado' => EstadoCuotaEnum::PENDIENTE,
            'registrado_por' => Auth::id(),
        ]);

        $this->agregandoCargo = false;
        $this->cargoConceptoNuevo = '';
        $this->cargoMontoNuevo = '';
    }

    /**
     * Los cursos del grado y ciclo de esta matrícula, cada uno con sus
     * horarios disponibles (por si tiene varias secciones), cuál está
     * asignado explícitamente (si alguno) y si hace falta elegir uno
     * ("ambiguo" = el curso tiene más de una sección, ver
     * Matricula::scopeDelHorario()).
     *
     * @return Collection<int, array{curso: Curso, opciones: Collection<int, Horario>, asignado: ?Horario, ambiguo: bool}>
     */
    private function cursosConHorarios(Matricula $matricula): Collection
    {
        $horariosPorCurso = Horario::query()
            ->where('ciclo_id', $matricula->ciclo_id)
            ->where('grado_id', $matricula->grado_id)
            ->with(['curso', 'docente', 'dias'])
            ->get()
            ->groupBy('curso_id');

        $asignadosPorCurso = $matricula->horarios->keyBy('curso_id');

        return $horariosPorCurso
            ->map(fn (Collection $opciones, int $cursoId) => [
                'curso' => $opciones->first()->curso,
                'opciones' => $opciones->sortBy(fn (Horario $horario) => $horario->diasResumen())->values(),
                'asignado' => $asignadosPorCurso->get($cursoId),
                'ambiguo' => $opciones->count() > 1,
            ])
            ->sortBy(fn (array $entrada) => $entrada['curso']->nombre)
            ->values();
    }

    public function with(PlanPagoService $planes): array
    {
        $estudiante = $this->estudianteId ? Estudiante::query()->with(['media', 'user.media', 'telefonos'])->find($this->estudianteId) : null;

        $matriculas = $estudiante?->matriculas()->with(['ciclo', 'grado', 'horarios.curso', 'horarios.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'estudiante' => $estudiante,
            'documentos' => $estudiante?->documentos()->with('media')->get(),
            'examenes' => $estudiante?->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'cursosConHorarios' => $matriculas?->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->cursosConHorarios($matricula)]) ?? collect(),
            'planesPorMatricula' => $matriculas !== null && Auth::user()->hasPermissionTo('pagos.ver')
                ? $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $planes->planDe($matricula)])
                : collect(),
            'cargosAdicionales' => $estudiante !== null && Auth::user()->hasPermissionTo('pagos.ver')
                ? CargoAdicional::query()->where('estudiante_id', $estudiante->id)->get()
                : collect(),
        ];
    }
}; ?>

<div>
    <x-modal name="ver-ficha" :tv="true" max-width="2xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <div>
                <h2 class="font-display text-lg text-ink">{{ $estudiante?->nombreCompleto() ?? 'Ficha del estudiante' }}</h2>
                @if ($estudiante)
                    <p class="text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
                @endif
            </div>
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="max-h-[75vh] overflow-y-auto p-6" wire:loading.class="opacity-50">
            @if ($estudiante)
                <x-matricula.ficha-estudiante
                    :estudiante="$estudiante"
                    :documentos="$documentos"
                    :examenes="$examenes"
                    :matriculas="$matriculas"
                    :cursos-con-horarios="$cursosConHorarios"
                    :editando-horario-matricula-id="$editandoHorarioMatriculaId"
                    :editando-horario-curso-id="$editandoHorarioCursoId"
                    :horario-seleccionado="$horarioSeleccionado"
                    :editando-fecha-fin-matricula-id="$editandoFechaFinMatriculaId"
                    :fecha-fin-estudio-nueva="$fechaFinEstudioNueva"
                    :planes-por-matricula="$planesPorMatricula"
                    :editando-monto-plan-id="$editandoMontoPlanId"
                    :monto-total-nuevo="$montoTotalNuevo"
                    :cargos-adicionales="$cargosAdicionales"
                    :editando-monto-cargo-id="$editandoMontoCargoId"
                    :monto-cargo-nuevo="$montoCargoNuevo"
                    :agregando-cargo="$agregandoCargo"
                    :cargo-concepto-nuevo="$cargoConceptoNuevo"
                    :cargo-monto-nuevo="$cargoMontoNuevo"
                />
            @else
                <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
            @endif
        </div>
    </x-modal>
</div>
