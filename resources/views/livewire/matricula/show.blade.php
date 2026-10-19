<?php

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\CursoService;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaCursoCortoEnum;
use App\Modules\Matricula\Enums\TipoCertificadoSubidoEnum;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\CertificadoSubido;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Modules\Matricula\Services\CertificadoSubidoService;
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
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Estudiante $estudiante;

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

    public bool $editandoDatosPersonales = false;

    public string $editNombres = '';

    public string $editApellidos = '';

    public string $editFechaNacimiento = '';

    public string $editEstadoCivil = '';

    public string $editDireccion = '';

    public string $editCelular = '';

    public string $editEmail = '';

    public string $editEstado = '';

    public bool $mostrandoFormDocumento = false;

    public string $documentoTipoNuevo = '';

    public $documentoArchivoNuevo = null;

    public $documentoReversoNuevo = null;

    public bool $mostrandoFormCursoCorto = false;

    public string $cursoCortoNuevoId = '';

    public string $fechaInicioCursoCortoNuevo = '';

    public bool $mostrandoFormCertificado = false;

    public string $certificadoTipoNuevo = '';

    public string $certificadoCursoNuevoId = '';

    public $certificadoArchivoNuevo = null;

    public function mount(Estudiante $estudiante): void
    {
        Gate::authorize('matricula.ver');

        $modalidad = auth()->user()->modalidadMatriculaRestringida();

        if ($modalidad !== null && ! Estudiante::query()->whereKey($estudiante->id)->deModalidad($modalidad)->exists()) {
            abort(403);
        }

        $this->estudiante = $estudiante->load(['media', 'user.media', 'telefonos']);
        $this->observacionesTexto = $estudiante->observaciones ?? '';
    }

    public function iniciarEdicionDatosPersonales(): void
    {
        Gate::authorize('matricula.editar');

        $this->editandoDatosPersonales = true;
        $this->editNombres = $this->estudiante->nombres;
        $this->editApellidos = $this->estudiante->apellidos;
        $this->editFechaNacimiento = $this->estudiante->fecha_nacimiento?->format('Y-m-d') ?? '';
        $this->editEstadoCivil = $this->estudiante->estado_civil?->value ?? '';
        $this->editDireccion = $this->estudiante->direccion ?? '';
        $this->editCelular = $this->estudiante->celular ?? '';
        $this->editEmail = $this->estudiante->email ?? '';
        $this->editEstado = $this->estudiante->estado->value;
    }

    public function cancelarEdicionDatosPersonales(): void
    {
        $this->editandoDatosPersonales = false;
    }

    public function guardarDatosPersonales(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        $datos = $this->validate([
            'editNombres' => 'required|string|max:100',
            'editApellidos' => 'required|string|max:100',
            'editFechaNacimiento' => 'nullable|date|before:today',
            'editEstadoCivil' => 'nullable|string|in:'.implode(',', array_column(EstadoCivilEnum::cases(), 'value')),
            'editDireccion' => 'nullable|string|max:150',
            'editCelular' => 'nullable|regex:/^9[0-9]{8}$/',
            'editEmail' => 'nullable|email|max:150',
            'editEstado' => 'required|string|in:'.implode(',', array_column(EstadoEstudianteEnum::cases(), 'value')),
        ]);

        $service->actualizarDatosPersonales($this->estudiante, [
            'nombres' => $datos['editNombres'],
            'apellidos' => $datos['editApellidos'],
            'fechaNacimiento' => $datos['editFechaNacimiento'] ?: null,
            'estadoCivil' => $datos['editEstadoCivil'] ?: null,
            'direccion' => $datos['editDireccion'] ?: null,
            'celular' => $datos['editCelular'] ?: null,
            'email' => $datos['editEmail'] ?: null,
            'estado' => $datos['editEstado'],
        ]);

        $this->editandoDatosPersonales = false;

        session()->flash('status', 'Datos personales actualizados.');
    }

    public function verificarDocumento(int $documentoId, DocumentoEstudianteService $service): void
    {
        Gate::authorize('matricula.editar');

        $documento = DocumentoEstudiante::query()->findOrFail($documentoId);
        $service->verificar($documento);

        session()->flash('status', 'Documento marcado como verificado.');
    }

    /**
     * $tipo precargado sirve tanto para "agregar" (vacío, elige el staff) como
     * para "reemplazar archivo" de un documento ya subido -- subir() en el
     * servicio ya hace updateOrCreate por tipo, así que es la misma acción.
     */
    public function mostrarFormDocumento(?string $tipo = null): void
    {
        Gate::authorize('matricula.editar');

        $this->resetValidation();
        $this->documentoTipoNuevo = $tipo ?? '';
        $this->documentoArchivoNuevo = null;
        $this->documentoReversoNuevo = null;
        $this->mostrandoFormDocumento = true;
    }

    public function cancelarFormDocumento(): void
    {
        $this->mostrandoFormDocumento = false;
        $this->documentoTipoNuevo = '';
        $this->documentoArchivoNuevo = null;
        $this->documentoReversoNuevo = null;
    }

    public function guardarDocumento(DocumentoEstudianteService $service): void
    {
        Gate::authorize('matricula.editar');

        $esDni = in_array($this->documentoTipoNuevo, [TipoDocumentoEnum::DNI_ESTUDIANTE->value, TipoDocumentoEnum::DNI_APODERADO->value], true);

        $this->validate([
            'documentoTipoNuevo' => 'required|string|in:'.implode(',', array_column(TipoDocumentoEnum::cases(), 'value')),
            'documentoArchivoNuevo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'documentoReversoNuevo' => $esDni ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096' : 'prohibited',
        ]);

        $service->subir(
            $this->estudiante,
            TipoDocumentoEnum::from($this->documentoTipoNuevo),
            $this->documentoArchivoNuevo,
            Auth::id(),
            $esDni ? $this->documentoReversoNuevo : null,
        );

        $this->cancelarFormDocumento();
        session()->flash('status', 'Documento guardado.');
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

        $this->estudiante->update(['observaciones' => $this->observacionesTexto ?: null]);

        session()->flash('status', 'Observaciones actualizadas.');
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

        session()->flash('status', 'Fin de estudios actualizado.');
    }

    public function finalizarCursoCorto(int $cursoCortoId, string $estado, MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        $cursoCorto = MatriculaCursoCorto::query()->findOrFail($cursoCortoId);

        $service->finalizarCursoCorto($cursoCorto, EstadoMatriculaCursoCortoEnum::from($estado));
    }

    public function mostrarFormCursoCorto(): void
    {
        Gate::authorize('matricula.editar');

        $this->resetValidation();
        $this->cursoCortoNuevoId = '';
        $this->fechaInicioCursoCortoNuevo = now()->format('Y-m-d');
        $this->mostrandoFormCursoCorto = true;
    }

    public function cancelarFormCursoCorto(): void
    {
        $this->mostrandoFormCursoCorto = false;
        $this->cursoCortoNuevoId = '';
        $this->fechaInicioCursoCortoNuevo = '';
    }

    public function guardarCursoCorto(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        $this->validate([
            'cursoCortoNuevoId' => 'required|integer|exists:cursos,id',
            'fechaInicioCursoCortoNuevo' => 'required|date',
        ]);

        $service->matricularCursoCorto($this->estudiante, [
            'curso_id' => (int) $this->cursoCortoNuevoId,
            'fecha_inicio' => $this->fechaInicioCursoCortoNuevo,
            'observaciones' => null,
            'registrado_por' => Auth::id(),
        ]);

        $this->cancelarFormCursoCorto();
        session()->flash('status', 'Curso corto registrado.');
    }

    public function mostrarFormCertificado(): void
    {
        Gate::authorize('matricula.editar');

        $this->resetValidation();
        $this->certificadoTipoNuevo = '';
        $this->certificadoCursoNuevoId = '';
        $this->certificadoArchivoNuevo = null;
        $this->mostrandoFormCertificado = true;
    }

    public function cancelarFormCertificado(): void
    {
        $this->mostrandoFormCertificado = false;
        $this->certificadoTipoNuevo = '';
        $this->certificadoCursoNuevoId = '';
        $this->certificadoArchivoNuevo = null;
    }

    public function guardarCertificado(CertificadoSubidoService $service): void
    {
        Gate::authorize('matricula.editar');

        $esCapacitacion = $this->certificadoTipoNuevo === TipoCertificadoSubidoEnum::CAPACITACION->value;

        $this->validate([
            'certificadoTipoNuevo' => 'required|string|in:'.implode(',', array_column(TipoCertificadoSubidoEnum::cases(), 'value')),
            'certificadoCursoNuevoId' => $esCapacitacion ? 'required|integer|exists:cursos,id' : 'nullable',
            'certificadoArchivoNuevo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $service->subir(
            $this->estudiante,
            TipoCertificadoSubidoEnum::from($this->certificadoTipoNuevo),
            $this->certificadoArchivoNuevo,
            $esCapacitacion ? (int) $this->certificadoCursoNuevoId : null,
            Auth::id(),
        );

        $this->cancelarFormCertificado();
        session()->flash('status', 'Certificado guardado.');
    }

    public function eliminarCertificado(int $certificadoId, CertificadoSubidoService $service): void
    {
        Gate::authorize('matricula.editar');

        $service->eliminar(CertificadoSubido::query()->findOrFail($certificadoId));

        session()->flash('status', 'Certificado eliminado.');
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

        session()->flash('status', 'Horario actualizado.');
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

        session()->flash('status', 'Monto del plan de pago actualizado.');
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

        session()->flash('status', 'Monto del cargo adicional actualizado.');
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

        $this->validate([
            'cargoConceptoNuevo' => 'required|string|max:100',
            'cargoMontoNuevo' => 'required|numeric|min:0.01',
        ]);

        CargoAdicional::query()->create([
            'estudiante_id' => $this->estudiante->id,
            'concepto' => $this->cargoConceptoNuevo,
            'monto' => (float) $this->cargoMontoNuevo,
            'estado' => EstadoCuotaEnum::PENDIENTE,
            'registrado_por' => Auth::id(),
        ]);

        $this->agregandoCargo = false;
        $this->cargoConceptoNuevo = '';
        $this->cargoMontoNuevo = '';

        session()->flash('status', 'Cargo adicional agregado.');
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

    public function with(PlanPagoService $planes, CursoService $cursos): array
    {
        $this->estudiante->refresh();

        $matriculas = $this->estudiante->matriculas()->with(['ciclo', 'grado', 'horarios.curso', 'horarios.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'documentos' => $this->estudiante->documentos()->with('media')->get(),
            'examenes' => $this->estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'cursosCortos' => $this->estudiante->cursosCortos()->with('curso')->latest('fecha_inicio')->get(),
            'cursosCortosDisponibles' => $cursos->deCapacitacion(),
            'certificadosSubidos' => $this->estudiante->certificadosSubidos()->with(['curso', 'media'])->latest()->get(),
            'cursosConHorarios' => $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->cursosConHorarios($matricula)]),
            'planesPorMatricula' => Auth::user()->hasPermissionTo('pagos.ver')
                ? $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $planes->planDe($matricula)])
                : collect(),
            'cargosAdicionales' => Auth::user()->hasPermissionTo('pagos.ver')
                ? CargoAdicional::query()->where('estudiante_id', $this->estudiante->id)->get()
                : collect(),
            'estadosCiviles' => EstadoCivilEnum::cases(),
            'estadosEstudiante' => EstadoEstudianteEnum::cases(),
            'tiposDocumento' => TipoDocumentoEnum::cases(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('matricula.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Matrícula</a>
        <div class="mt-1 flex items-center gap-3">
            <h1 class="font-display text-2xl text-ink">{{ $estudiante->nombreCompleto() }}</h1>
            <span @class([
                'rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
            ])>
                {{ $estudiante->estado->label() }}
            </span>
        </div>
        <p class="mt-1 text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <x-matricula.ficha-estudiante
        :estudiante="$estudiante"
        :documentos="$documentos"
        :examenes="$examenes"
        :matriculas="$matriculas"
        :cursos-cortos="$cursosCortos"
        :cursos-cortos-disponibles="$cursosCortosDisponibles"
        :mostrando-form-curso-corto="$mostrandoFormCursoCorto"
        :curso-corto-nuevo-id="$cursoCortoNuevoId"
        :fecha-inicio-curso-corto-nuevo="$fechaInicioCursoCortoNuevo"
        :certificados-subidos="$certificadosSubidos"
        :mostrando-form-certificado="$mostrandoFormCertificado"
        :certificado-tipo-nuevo="$certificadoTipoNuevo"
        :certificado-curso-nuevo-id="$certificadoCursoNuevoId"
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
        :editando-datos-personales="$editandoDatosPersonales"
        :edit-nombres="$editNombres"
        :edit-apellidos="$editApellidos"
        :edit-fecha-nacimiento="$editFechaNacimiento"
        :edit-estado-civil="$editEstadoCivil"
        :edit-direccion="$editDireccion"
        :edit-celular="$editCelular"
        :edit-email="$editEmail"
        :edit-estado="$editEstado"
        :estados-civiles="$estadosCiviles"
        :estados-estudiante="$estadosEstudiante"
        :mostrando-form-documento="$mostrandoFormDocumento"
        :documento-tipo-nuevo="$documentoTipoNuevo"
        :tipos-documento="$tiposDocumento"
    />
</div>
