<?php

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Siagie;
use App\Modules\Academico\Services\CicloService;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\ExamenUbicacionService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    // Paso 1 — estudiante
    public string $nombres = '';

    public string $apellidos = '';

    public string $dni = '';

    public string $fechaNacimiento = '';

    public string $estadoCivil = '';

    public string $direccion = '';

    public string $celular = '';

    /**
     * Celulares adicionales, solo para mayores de edad (ver
     * EstudianteTelefono) -- el menor de edad se comunica a través del
     * celular del apoderado, no tiene sentido pedirle más de uno propio.
     *
     * @var list<string>
     */
    public array $celularesAdicionales = [];

    public string $observacionesEstudiante = '';

    public $foto = null;

    // Rematrícula — cuando el DNI tecleado en el paso 1 ya pertenece a un
    // estudiante existente, en vez de rechazarlo se ofrece continuar con su
    // ficha ya creada, saltando directo a elegir ciclo/grado.
    public ?int $estudianteEncontradoId = null;

    public bool $esRematricula = false;

    // Paso 2 — apoderado (condicional)
    public string $apoderadoNombres = '';

    public string $apoderadoDni = '';

    public string $apoderadoCelular = '';

    public string $apoderadoCorreo = '';

    public string $apoderadoDireccion = '';

    public string $apoderadoParentesco = '';

    // Paso 3 — documentos e institución de procedencia
    public $dniEstudianteCaraArchivo = null;

    public $dniEstudianteReversoArchivo = null;

    public $dniApoderadoCaraArchivo = null;

    public $dniApoderadoReversoArchivo = null;

    public $certificadoArchivo = null;

    public $constanciaArchivo = null;

    public string $colegioNombre = '';

    public string $colegioUbicacion = '';

    public string $colegioAnioEgreso = '';

    // Paso 4 — examen de ubicación (opcional)
    public bool $registrarExamen = false;

    public string $examenFecha = '';

    public string $examenCosto = '';

    public string $examenResultado = '';

    public string $examenGradoAsignadoId = '';

    public string $examenObservaciones = '';

    // Paso 5 — matrícula
    public string $modalidadCiclo = 'seis_meses';

    public string $cicloId = '';

    public string $gradoId = '';

    public string $siagieId = '';

    public string $fechaMatricula = '';

    public string $observacionesMatricula = '';

    // Paso 6 — cronograma de pagos (opcional)
    public bool $configurarCronograma = false;

    public string $numeroCuotasCronograma = '6';

    public string $montoTotalCronograma = '';

    /** @var array<int, string> */
    public array $cuotaMontos = [];

    /** @var array<int, string> */
    public array $cuotaFechas = [];

    // Paso 6 — cargos adicionales puntuales (opcional, aparte de la
    // mensualidad): Convalidación, Exoneración, Recuperación, Visación...
    // concepto y monto libres, editables caso por caso por estudiante.
    /** @var array<int, array{concepto: string, monto: string}> */
    public array $cargosAdicionales = [];

    public function mount(): void
    {
        Gate::authorize('matricula.crear');

        $this->fechaMatricula = now()->format('Y-m-d');
    }

    #[Computed]
    public function esMenorDeEdad(): bool
    {
        if ($this->fechaNacimiento === '') {
            return false;
        }

        return MatriculaService::esMenorDeEdad($this->fechaNacimiento);
    }

    public function agregarCelular(): void
    {
        $this->celularesAdicionales[] = '';
    }

    public function quitarCelular(int $indice): void
    {
        unset($this->celularesAdicionales[$indice]);
        $this->celularesAdicionales = array_values($this->celularesAdicionales);
    }

    /**
     * Se dispara en cada cambio del DNI (wire:model.live) para avisar de
     * inmediato si ya pertenece a un estudiante existente, en vez de
     * esperar a que se intente avanzar de paso.
     */
    public function updatedDni(): void
    {
        $dni = trim($this->dni);
        $this->estudianteEncontradoId = $dni !== '' ? Estudiante::query()->where('dni', $dni)->value('id') : null;
    }

    #[Computed]
    public function estudianteEncontrado(): ?Estudiante
    {
        return $this->estudianteEncontradoId
            ? Estudiante::query()->with('gradoActual')->find($this->estudianteEncontradoId)
            : null;
    }

    /**
     * El estudiante ya tiene ficha (datos, apoderado, documentos, examen de
     * ubicación): no hace falta volver a pedir nada de eso, solo el
     * ciclo/grado de la nueva matrícula.
     */
    public function continuarComoRematricula(): void
    {
        if (! $this->estudianteEncontradoId) {
            return;
        }

        $this->esRematricula = true;
        $this->resetValidation();

        $ultimaMatricula = Matricula::query()
            ->where('estudiante_id', $this->estudianteEncontradoId)
            ->latest('fecha_matricula')
            ->with('ciclo')
            ->first();

        $this->modalidadCiclo = $ultimaMatricula?->ciclo?->modalidad->value ?? 'seis_meses';

        $this->paso = 5;
    }

    /**
     * Al cambiar de modalidad se limpia el ciclo elegido; para SIAGIE anual
     * no hay selector -- se autoasigna el ciclo anual vigente, si existe
     * (ver CicloService::cicloAnualVigente() y with()). A diferencia de
     * los Grupos de 6 meses, no depende de un periodo de matrícula abierto.
     */
    public function updatedModalidadCiclo(CicloService $ciclos): void
    {
        $this->cicloId = '';

        if ($this->modalidadCiclo === ModalidadCicloEnum::ANUAL->value) {
            $cicloAnual = $ciclos->cicloAnualVigente();
            $this->cicloId = $cicloAnual !== null ? (string) $cicloAnual->id : '';
        }
    }

    public function avanzar(MatriculaService $service): void
    {
        if ($this->paso === 1) {
            $this->validate([
                'nombres' => 'required|string|max:100',
                'apellidos' => 'required|string|max:100',
                'dni' => 'required|string|min:8|max:12',
                'fechaNacimiento' => 'nullable|date|before:today',
                'estadoCivil' => 'nullable|string|in:'.implode(',', array_column(EstadoCivilEnum::cases(), 'value')),
                'direccion' => 'nullable|string|max:150',
                'celular' => 'nullable|string',
                'celularesAdicionales.*' => 'nullable|string',
            ]);

            if (! $service->dniDisponible($this->dni)) {
                $this->addError('dni', 'Ya existe un estudiante registrado con este DNI. Usa el botón de rematrícula de abajo para continuar con su ficha.');

                return;
            }

            $this->paso = $this->esMenorDeEdad ? 2 : 3;

            return;
        }

        if ($this->paso === 2) {
            $this->validate([
                'apoderadoNombres' => 'required|string|max:150',
                'apoderadoDni' => 'required|string|min:8|max:12',
                'apoderadoCelular' => 'required|string',
                'apoderadoCorreo' => 'nullable|email|max:150',
                'apoderadoDireccion' => 'nullable|string|max:150',
                'apoderadoParentesco' => 'required|string|max:50',
            ]);

            $this->paso = 3;

            return;
        }

        if ($this->paso === 3) {
            $this->validate([
                'dniEstudianteCaraArchivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'dniEstudianteReversoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'dniApoderadoCaraArchivo' => $this->esMenorDeEdad ? 'required|file|mimes:pdf,jpg,jpeg,png|max:4096' : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'dniApoderadoReversoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'certificadoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'constanciaArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'colegioNombre' => 'nullable|string|max:150',
                'colegioUbicacion' => 'nullable|string|max:150',
                'colegioAnioEgreso' => 'nullable|integer|min:1950|max:'.now()->year,
            ]);

            $this->paso = 4;

            return;
        }

        if ($this->paso === 4) {
            if ($this->registrarExamen) {
                $this->validate([
                    'examenFecha' => 'required|date',
                    'examenCosto' => 'required|numeric|min:0',
                    'examenResultado' => 'nullable|string|max:30',
                    'examenGradoAsignadoId' => 'nullable|integer|exists:grados,id',
                    'examenObservaciones' => 'nullable|string',
                ]);
            }

            $this->paso = 5;

            return;
        }

        if ($this->paso === 5) {
            $this->validate([
                'modalidadCiclo' => 'required|string|in:seis_meses,anual',
                'cicloId' => 'required|integer|exists:ciclos,id',
                'gradoId' => 'required|integer|exists:grados,id',
                'siagieId' => 'nullable|integer|exists:siagies,id',
                'fechaMatricula' => 'required|date',
            ]);

            $this->paso = 6;
        }
    }

    /**
     * Reparte montoTotalCronograma en partes iguales entre las cuotas
     * elegidas (la última absorbe el redondeo), con vencimientos mensuales
     * desde hoy como aproximación -- la fecha real de matrícula recién se
     * fija al confirmar. Es solo el punto de partida: cada monto y fecha
     * queda editable después para ajustarlo caso por caso.
     */
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
        $this->cargosAdicionales[] = ['concepto' => '', 'monto' => ''];
    }

    public function quitarCargoAdicional(int $indice): void
    {
        unset($this->cargosAdicionales[$indice]);
        $this->cargosAdicionales = array_values($this->cargosAdicionales);
    }

    public function retroceder(): void
    {
        if ($this->esRematricula && $this->paso === 5) {
            $this->esRematricula = false;
            $this->paso = 1;

            return;
        }

        if ($this->paso === 3 && ! $this->esMenorDeEdad) {
            $this->paso = 1;

            return;
        }

        $this->paso = max(1, $this->paso - 1);
    }

    public function cancelar(): void
    {
        $this->dispatch('wizard-cerrado');
    }

    public function confirmar(
        MatriculaService $matriculaService,
        DocumentoEstudianteService $documentoService,
        ExamenUbicacionService $examenService,
        PlanPagoService $planPagoService,
    ): void {
        Gate::authorize('matricula.crear');

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

        // Cada fila de cargo adicional necesita concepto Y monto juntos --
        // una fila con solo uno de los dos es casi seguro un olvido, no un
        // cargo válido que crear.
        foreach ($this->cargosAdicionales as $indice => $cargo) {
            $tieneConcepto = trim($cargo['concepto']) !== '';
            $tieneMonto = $cargo['monto'] !== '';

            if ($tieneConcepto !== $tieneMonto) {
                $this->addError("cargosAdicionales.{$indice}.concepto", 'Completa el concepto y el monto de este cargo, o quítalo.');

                return;
            }
        }

        // Rematrícula: la ficha (datos, apoderado, documentos, examen de
        // ubicación) ya existe -- solo hace falta la nueva matrícula (y su
        // cronograma opcional), reutilizando matricular() tal cual la usa
        // matricularDesdeFilas() para la carga masiva.
        if ($this->esRematricula) {
            $estudiante = DB::transaction(function () use ($matriculaService, $planPagoService) {
                $estudiante = Estudiante::query()->findOrFail($this->estudianteEncontradoId);

                $matricula = $matriculaService->matricular($estudiante, new RegistrarMatriculaData(
                    cicloId: (int) $this->cicloId,
                    gradoId: (int) $this->gradoId,
                    observaciones: $this->observacionesMatricula ?: null,
                    registradoPor: auth()->id(),
                    siagieId: $this->siagieId !== '' ? (int) $this->siagieId : null,
                    fechaMatricula: $this->fechaMatricula !== '' ? $this->fechaMatricula : null,
                ));

                $this->crearCronogramaSiCorresponde($matricula, $planPagoService);
                $this->crearCargosAdicionalesSiCorresponde($estudiante);

                return $estudiante;
            });

            $this->dispatch('matricula-registrada', estudianteId: $estudiante->id, nombre: $estudiante->nombreCompleto());

            return;
        }

        // Todo el registro (estudiante, apoderado, documentos, examen y
        // matrícula) va en una sola transacción: si matricular() falla al
        // final (grado incoherente con la edad, periodo cerrado, sección
        // faltante, etc.), el estudiante recién creado NO debe quedar
        // huérfano en la base de datos -- si quedara, un reintento desde
        // este mismo paso 5 (sin volver al paso 1) chocaría con el DNI ya
        // registrado y rompería con un error sin manejar.
        $estudiante = DB::transaction(function () use ($matriculaService, $documentoService, $examenService, $planPagoService) {
            $estudiante = $matriculaService->registrarEstudiante(new RegistrarEstudianteData(
                nombres: $this->nombres,
                apellidos: $this->apellidos,
                dni: new Dni($this->dni),
                fechaNacimiento: $this->fechaNacimiento !== '' ? $this->fechaNacimiento : null,
                estadoCivil: $this->estadoCivil !== '' ? EstadoCivilEnum::from($this->estadoCivil) : null,
                direccion: $this->direccion ?: null,
                celular: $this->celular !== '' ? new Telefono($this->celular) : null,
                observaciones: $this->observacionesEstudiante ?: null,
            ));

            if ($this->foto) {
                $estudiante->addMedia($this->foto->getRealPath())
                    ->usingFileName('foto-'.$estudiante->id.'.'.$this->foto->getClientOriginalExtension())
                    ->preservingOriginal()
                    ->toMediaCollection('foto');
            }

            if ($this->esMenorDeEdad) {
                $matriculaService->registrarApoderado($estudiante, new RegistrarApoderadoData(
                    nombres: $this->apoderadoNombres,
                    dni: new Dni($this->apoderadoDni),
                    celular: new Telefono($this->apoderadoCelular),
                    correo: $this->apoderadoCorreo ?: null,
                    direccion: $this->apoderadoDireccion ?: null,
                    parentesco: $this->apoderadoParentesco,
                ));
            }

            if ($this->colegioNombre !== '') {
                $matriculaService->registrarInstitucionProcedencia($estudiante, [
                    'nombre_colegio' => $this->colegioNombre,
                    'ubicacion' => $this->colegioUbicacion ?: null,
                    'anio_egreso' => $this->colegioAnioEgreso !== '' ? (int) $this->colegioAnioEgreso : null,
                ]);
            }

            if ($this->dniEstudianteCaraArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_ESTUDIANTE, $this->dniEstudianteCaraArchivo, auth()->id(), $this->dniEstudianteReversoArchivo);
            }
            if ($this->dniApoderadoCaraArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_APODERADO, $this->dniApoderadoCaraArchivo, auth()->id(), $this->dniApoderadoReversoArchivo);
            }
            if ($this->certificadoArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, $this->certificadoArchivo, auth()->id());
            }
            if ($this->constanciaArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CONSTANCIA, $this->constanciaArchivo, auth()->id());
            }

            foreach ($this->celularesAdicionales as $numero) {
                if ($numero !== '') {
                    $estudiante->telefonos()->create(['numero' => (string) new Telefono($numero)]);
                }
            }

            if ($this->registrarExamen) {
                $examenService->registrar($estudiante, [
                    'fecha' => $this->examenFecha,
                    'costo' => (float) $this->examenCosto,
                    'resultado' => $this->examenResultado ?: null,
                    'grado_asignado_id' => $this->examenGradoAsignadoId !== '' ? (int) $this->examenGradoAsignadoId : null,
                    'observaciones' => $this->examenObservaciones ?: null,
                ]);
            }

            $matricula = $matriculaService->matricular($estudiante, new RegistrarMatriculaData(
                cicloId: (int) $this->cicloId,
                gradoId: (int) $this->gradoId,
                observaciones: $this->observacionesMatricula ?: null,
                registradoPor: auth()->id(),
                siagieId: $this->siagieId !== '' ? (int) $this->siagieId : null,
                fechaMatricula: $this->fechaMatricula !== '' ? $this->fechaMatricula : null,
            ));

            $this->crearCronogramaSiCorresponde($matricula, $planPagoService);
            $this->crearCargosAdicionalesSiCorresponde($estudiante);

            return $estudiante;
        });

        $this->dispatch('matricula-registrada', estudianteId: $estudiante->id, nombre: $estudiante->nombreCompleto());
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
                'estado' => EstadoCuotaEnum::PENDIENTE,
                'registrado_por' => auth()->id(),
            ]);
        }
    }

    public function with(CicloService $ciclos): array
    {
        $grados = Grado::query()->where('activo', true)->with('programaEstudio')->orderBy('orden')->get();

        $ciclosConMatriculaAbierta = Ciclo::query()
            ->where('modalidad', ModalidadCicloEnum::SEIS_MESES)
            ->whereHas('periodosMatricula', function ($query) {
                $query->where('estado', 'abierto')
                    ->where('fecha_inicio', '<=', now())
                    ->where('fecha_fin', '>=', now());
            })
            ->orderByDesc('fecha_inicio')
            ->get();

        return [
            'estadosCiviles' => EstadoCivilEnum::cases(),
            'gradosCompatibles' => $grados,
            'todosLosGrados' => $grados,
            'modalidadesCiclo' => ModalidadCicloEnum::cases(),
            'siagiesDisponibles' => Siagie::query()->orderByDesc('anio')->orderBy('tipo')->get(),
            'ciclosDisponibles' => $ciclosConMatriculaAbierta,
            'cicloAnualVigente' => $ciclos->cicloAnualVigente(),
            'numerosCuotas' => NumeroCuotasEnum::cases(),
        ];
    }
}; ?>

<div
    x-data="{ mostrar: false }"
    x-init="$nextTick(() => mostrar = true)"
    x-show="mostrar"
    x-cloak
    @class([
        'fixed inset-0 z-50 flex justify-center overflow-y-auto bg-ink/40 px-4 py-8',
        'items-center' => $esRematricula,
        'items-start' => ! $esRematricula,
    ])
    x-on:click.self="mostrar = false; setTimeout(() => $wire.cancelar(), 200)"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        x-show="mostrar"
        class="w-full max-w-3xl rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl text-ink">{{ $esRematricula ? 'Rematrícula' : 'Nueva matrícula' }}</h1>
                <p class="mt-1 text-sm text-ink-dim">
                    @if ($esRematricula)
                        Elige período de matrícula y semestre para continuar.
                    @else
                        Paso {{ $paso }} de 6
                    @endif
                </p>
            </div>
            <button
                type="button"
                x-on:click="mostrar = false; setTimeout(() => $wire.cancelar(), 200)"
                class="rounded-md p-1 text-ink-faint hover:bg-surface-2 hover:text-ink"
                aria-label="Cerrar"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        @unless ($esRematricula)
            <div class="mb-6 mt-4 flex gap-1">
                @for ($i = 1; $i <= 6; $i++)
                    <div @class(['h-1.5 flex-1 rounded-full', 'bg-accent' => $i <= $paso, 'bg-surface-2' => $i > $paso])></div>
                @endfor
            </div>
        @endunless

        <div>
        {{-- Paso 1: Estudiante --}}
        @if ($paso === 1)
            <h2 class="font-display text-lg text-ink">Datos del estudiante</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="nombres" value="Nombres" />
                    <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apellidos" value="Apellidos" />
                    <x-text-input wire:model="apellidos" id="apellidos" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apellidos')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dni" value="DNI" />
                    <x-text-input wire:model.live="dni" id="dni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                </div>
                @if ($this->estudianteEncontrado)
                    <div class="sm:col-span-2 rounded-md border border-accent/30 bg-accent-soft/40 p-3 text-sm">
                        <p class="font-medium text-ink">Ya existe un estudiante con este DNI: {{ $this->estudianteEncontrado->nombreCompleto() }}</p>
                        <p class="mt-1 text-ink-dim">Semestre actual: {{ $this->estudianteEncontrado->gradoActual?->nombre ?? 'sin semestre asignado' }}</p>
                        <p class="mt-2 text-xs text-ink-faint">Si vuelve a matricularse (rematrícula), no hace falta llenar sus datos otra vez — solo el período de matrícula y semestre nuevos.</p>
                        <x-secondary-button type="button" wire:click="continuarComoRematricula" class="mt-2">
                            Rematricular a este estudiante
                        </x-secondary-button>
                    </div>
                @endif
                <div>
                    <x-input-label for="fechaNacimiento" value="Fecha de nacimiento (opcional)" />
                    <x-date-input wire:model.live="fechaNacimiento" id="fechaNacimiento" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaNacimiento')" class="mt-1" />
                    @if ($fechaNacimiento)
                        <p class="mt-1 text-xs text-ink-faint">{{ $this->esMenorDeEdad ? 'Menor de edad — se pedirán datos del apoderado.' : 'Mayor de edad.' }}</p>
                    @endif
                </div>
                <div>
                    <x-input-label for="estadoCivil" value="Estado civil (opcional)" />
                    <x-select-input
                        wire:model="estadoCivil"
                        id="estadoCivil"
                        class="mt-1 block w-full"
                        :options="collect($estadosCiviles)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])->prepend('Sin especificar', '')"
                    />
                </div>
                <div>
                    <x-input-label for="celular" value="Celular" />
                    <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                </div>
                @unless ($this->esMenorDeEdad)
                    <div class="sm:col-span-2">
                        <x-input-label value="Celulares adicionales (opcional)" />
                        <div class="mt-1 space-y-2">
                            @foreach ($celularesAdicionales as $indice => $numero)
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model="celularesAdicionales.{{ $indice }}" class="block w-full" />
                                    <button type="button" wire:click="quitarCelular({{ $indice }})" class="text-sm text-danger hover:underline">Quitar</button>
                                </div>
                                <x-input-error :messages="$errors->get('celularesAdicionales.'.$indice)" />
                            @endforeach
                        </div>
                        <button type="button" wire:click="agregarCelular" class="mt-2 text-sm font-medium text-accent hover:underline">+ Agregar otro celular</button>
                    </div>
                @endunless
                <div class="sm:col-span-2">
                    <x-input-label for="direccion" value="Dirección" />
                    <x-text-input wire:model="direccion" id="direccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('direccion')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Correo" />
                    <div class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 font-mono text-sm text-ink-dim">
                        {{ trim($dni) !== '' ? strtolower(trim($dni)).'@ceba.test' : '—' }}
                    </div>
                    <p class="mt-1 text-xs text-ink-faint">Se asigna automáticamente a partir del DNI, junto con su cuenta de acceso al sistema.</p>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="foto" value="Fotografía (opcional)" />
                    <input wire:model="foto" id="foto" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('foto')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesEstudiante" value="Observaciones (opcional)" />
                    <p class="mt-1 text-xs text-ink-faint">Acuerdos especiales, documentos pendientes de entregar, casos particulares (ej. examen de ubicación).</p>
                    <textarea wire:model="observacionesEstudiante" id="observacionesEstudiante" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('observacionesEstudiante')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 2: Apoderado --}}
        @if ($paso === 2)
            <h2 class="font-display text-lg text-ink">Datos del apoderado</h2>
            <p class="mt-1 text-sm text-ink-dim">El estudiante es menor de edad, así que necesitamos los datos de su apoderado.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoNombres" value="Nombres completos" />
                    <x-text-input wire:model="apoderadoNombres" id="apoderadoNombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoNombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoDni" value="DNI" />
                    <x-text-input wire:model="apoderadoDni" id="apoderadoDni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoParentesco" value="Parentesco" />
                    <x-text-input wire:model="apoderadoParentesco" id="apoderadoParentesco" class="mt-1 block w-full" placeholder="Madre, padre, tío…" />
                    <x-input-error :messages="$errors->get('apoderadoParentesco')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCelular" value="Celular" />
                    <x-text-input wire:model="apoderadoCelular" id="apoderadoCelular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCelular')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCorreo" value="Correo (opcional)" />
                    <x-text-input wire:model="apoderadoCorreo" id="apoderadoCorreo" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCorreo')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoDireccion" value="Dirección (opcional)" />
                    <x-text-input wire:model="apoderadoDireccion" id="apoderadoDireccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDireccion')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 3: Documentos e institución de procedencia --}}
        @if ($paso === 3)
            <h2 class="font-display text-lg text-ink">Documentos e institución de procedencia</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="dniEstudianteCaraArchivo" value="DNI del estudiante — cara" />
                    <input wire:model="dniEstudianteCaraArchivo" id="dniEstudianteCaraArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('dniEstudianteCaraArchivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dniEstudianteReversoArchivo" value="DNI del estudiante — sello/reverso (opcional)" />
                    <input wire:model="dniEstudianteReversoArchivo" id="dniEstudianteReversoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('dniEstudianteReversoArchivo')" class="mt-1" />
                </div>
                @if ($this->esMenorDeEdad)
                    <div>
                        <x-input-label for="dniApoderadoCaraArchivo" value="DNI del apoderado — cara" />
                        <input wire:model="dniApoderadoCaraArchivo" id="dniApoderadoCaraArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('dniApoderadoCaraArchivo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="dniApoderadoReversoArchivo" value="DNI del apoderado — sello/reverso (opcional)" />
                        <input wire:model="dniApoderadoReversoArchivo" id="dniApoderadoReversoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('dniApoderadoReversoArchivo')" class="mt-1" />
                    </div>
                @endif
                <div>
                    <x-input-label for="certificadoArchivo" value="Certificado de estudios (opcional)" />
                    <input wire:model="certificadoArchivo" id="certificadoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('certificadoArchivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="constanciaArchivo" value="Constancia (opcional)" />
                    <input wire:model="constanciaArchivo" id="constanciaArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('constanciaArchivo')" class="mt-1" />
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-ink">Institución de procedencia (opcional)</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="colegioNombre" value="Colegio anterior" />
                    <x-text-input wire:model="colegioNombre" id="colegioNombre" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioNombre')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioUbicacion" value="Ubicación" />
                    <x-text-input wire:model="colegioUbicacion" id="colegioUbicacion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioUbicacion')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioAnioEgreso" value="Año de egreso" />
                    <x-text-input wire:model="colegioAnioEgreso" id="colegioAnioEgreso" type="number" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioAnioEgreso')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 4: Examen de ubicación --}}
        @if ($paso === 4)
            <h2 class="font-display text-lg text-ink">Examen de ubicación</h2>
            <label class="mt-4 flex items-center gap-2 text-sm text-ink-dim">
                <input type="checkbox" wire:model.live="registrarExamen" class="rounded border-border text-accent focus:ring-accent">
                Este estudiante rindió examen de ubicación
            </label>

            @if ($registrarExamen)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="examenFecha" value="Fecha" />
                        <x-date-input wire:model="examenFecha" id="examenFecha" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('examenFecha')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenCosto" value="Costo (S/)" />
                        <x-text-input wire:model="examenCosto" id="examenCosto" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('examenCosto')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenResultado" value="Resultado" />
                        <x-text-input wire:model="examenResultado" id="examenResultado" class="mt-1 block w-full" placeholder="Ej. Apto" />
                        <x-input-error :messages="$errors->get('examenResultado')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenGradoAsignadoId" value="Programa y semestre asignado" />
                        <x-select-input
                            wire:model="examenGradoAsignadoId"
                            id="examenGradoAsignadoId"
                            class="mt-1 block w-full"
                            :options="collect($todosLosGrados)->mapWithKeys(fn ($grado) => [$grado->id => \"{$grado->programaEstudio->nombre} — {$grado->nombre}\"])->prepend('Sin asignar', '')"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="examenObservaciones" value="Observaciones" />
                        <textarea wire:model="examenObservaciones" id="examenObservaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                </div>
            @endif
        @endif

        {{-- Paso 5: Matrícula --}}
        @if ($paso === 5)
            <h2 class="font-display text-lg text-ink">Matrícula</h2>
            @if ($esRematricula && $this->estudianteEncontrado)
                <p class="mt-1 text-sm text-ink-dim">
                    Rematriculando a <span class="font-medium text-ink">{{ $this->estudianteEncontrado->nombreCompleto() }}</span>
                    (DNI {{ $this->estudianteEncontrado->dni }}).
                </p>
            @endif
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="modalidadCiclo" value="Modalidad" />
                    <x-select-input
                        wire:model.live="modalidadCiclo"
                        id="modalidadCiclo"
                        class="mt-1 block w-full"
                        :options="collect($modalidadesCiclo)->mapWithKeys(fn ($modalidad) => [$modalidad->value => $modalidad->label()])"
                    />
                    <x-input-error :messages="$errors->get('modalidadCiclo')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="siagieId" value="Periodo académico (opcional)" />
                    <x-select-input
                        wire:model="siagieId"
                        id="siagieId"
                        placeholder="Sin registrar…"
                        class="mt-1 block w-full"
                        :options="collect($siagiesDisponibles)->mapWithKeys(fn ($siagie) => [$siagie->id => $siagie->nombreCompleto()])"
                    />
                    <p class="mt-1 text-xs text-ink-faint">Independiente del Período de Matrícula: es la clasificación propia del periodo académico.</p>
                    <x-input-error :messages="$errors->get('siagieId')" class="mt-1" />
                </div>
                @if ($modalidadCiclo === 'anual')
                    <div>
                        <x-input-label value="Año" />
                        @if ($cicloAnualVigente)
                            <p class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-ink">{{ $cicloAnualVigente->anio }}</p>
                        @else
                            <p class="mt-1 text-xs text-danger">No hay ningún periodo académico anual registrado todavía. Créalo primero en Ciclos.</p>
                        @endif
                        <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                    </div>
                @else
                    <div>
                        <x-input-label for="cicloId" value="Período de matrícula" />
                        <x-select-input
                            wire:model.live="cicloId"
                            id="cicloId"
                            class="mt-1 block w-full"
                            :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                        />
                        @if ($ciclosDisponibles->isEmpty())
                            <p class="mt-1 text-xs text-danger">Ningún período de matrícula tiene la inscripción abierta hoy.</p>
                        @endif
                        <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                    </div>
                @endif
                <div>
                    <x-input-label for="gradoId" value="Programa de estudio y semestre" />
                    <x-select-input
                        wire:model.live="gradoId"
                        id="gradoId"
                        class="mt-1 block w-full"
                        :options="collect($gradosCompatibles)->mapWithKeys(fn ($grado) => [$grado->id => \"{$grado->programaEstudio->nombre} — {$grado->nombre}\"])"
                    />
                    <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="fechaMatricula" value="Fecha de matrícula" />
                    <x-date-input wire:model="fechaMatricula" id="fechaMatricula" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaMatricula')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesMatricula" value="Observaciones (opcional)" />
                    <textarea wire:model="observacionesMatricula" id="observacionesMatricula" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                </div>
            </div>
        @endif

        {{-- Paso 6: Cronograma de pagos --}}
        @if ($paso === 6)
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
        @endif

        <div class="mt-6 flex justify-between border-t border-border pt-4">
            @if ($paso > 1)
                <x-secondary-button type="button" wire:click="retroceder">Atrás</x-secondary-button>
            @else
                <span></span>
            @endif

            @if ($paso < 6)
                <x-primary-button type="button" wire:click="avanzar">Continuar</x-primary-button>
            @else
                <x-primary-button type="button" wire:click="confirmar">{{ $esRematricula ? 'Confirmar rematrícula' : 'Confirmar matrícula' }}</x-primary-button>
            @endif
        </div>
        </div>
    </div>
</div>
