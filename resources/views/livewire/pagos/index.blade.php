<?php

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\CobranzaService;
use App\Modules\Pagos\Services\ConceptoPagoService;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'aprobacion';

    // Registrar pago directo
    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public string $conceptoId = '';

    // Cuando se elige cobrar un cargo adicional puntual (Convalidación,
    // Exoneración...) en vez de un concepto del catálogo -- excluyentes
    // entre sí, ver seleccionarCargoAdicional()/limpiarCargoAdicional().
    public ?int $cargoAdicionalId = null;

    public string $detalle = '';

    public string $observacion = '';

    // Fecha en la que se recibió el pago (editable) -- distinta de la
    // fecha de emisión del recibo, que siempre es el momento en que
    // Tesorería aprueba y no se toca. Por defecto hoy, para el caso común.
    public string $fechaPago = '';

    // Un pago puede cubrirse con más de un medio a la vez (ej. una parte en
    // efectivo y otra por Yape): cada elemento es un monto+método
    // independiente, pero todas juntas quedan como un solo registro de
    // Pago (ver PagoService::registrar()).
    /** @var array<int, array{monto: string, metodo: string, nota: string}> */
    public array $partes = [
        ['monto' => '', 'metodo' => '', 'nota' => ''],
    ];

    public $comprobante = null;

    // Rechazo de pago
    /** @var array<int, string> */
    public array $motivoRechazo = [];

    // Serie elegida a mano para el recibo de cada pago en la cola de
    // aprobación (001 "Recibo de pago" o 002 "Recibo"), indexado por
    // pago_id igual que motivoRechazo.
    /** @var array<int, string> */
    public array $serieElegida = [];

    // Crear plan de pago — indexado por matricula_id, porque el listado
    // de "matrículas sin plan" puede mostrar varias filas a la vez.
    /** @var array<int, string> */
    public array $numeroCuotasPorMatricula = [];

    /** @var array<int, string> */
    public array $montoTotalPorMatricula = [];

    // Filtro de historial
    public string $filtroEstado = '';

    // Cobros — individual o grupal
    public string $cobrosModo = 'individual';

    public string $cobrosTerminoBusqueda = '';

    public ?int $cobrosEstudianteId = null;

    public string $cobrosEstudianteNombre = '';

    public string $cobrosCicloId = '';

    public string $cobrosGradoId = '';

    public string $cobrosCursoId = '';

    public string $cobrosFranja = '';

    /** @var array<int, string> */
    public array $cobrosConceptoIds = [];

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar']),
            403,
        );

        $this->tab = match (true) {
            $user->hasPermissionTo('pagos.aprobar') => 'aprobacion',
            $user->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']) => 'registrar',
            default => 'historial',
        };

        $this->fechaPago = now()->format('Y-m-d');
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre, CobranzaService $cobranza): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';
        $this->cargoAdicionalId = null;

        $this->autocompletarMontoConSaldo($cobranza);
    }

    public function updatedConceptoId(CobranzaService $cobranza): void
    {
        $this->autocompletarMontoConSaldo($cobranza);
    }

    /**
     * Elegir un cargo adicional pendiente es excluyente con elegir un
     * concepto del catálogo -- ver registrarPago(), que salta la
     * validación/uso de conceptoId cuando cargoAdicionalId está fijado.
     * Autocompleta el monto de la primera parte con el saldo real que
     * falta, mismo criterio que autocompletarMontoConSaldo() para cuotas.
     */
    public function seleccionarCargoAdicional(int $cargoAdicionalId): void
    {
        $this->cargoAdicionalId = $cargoAdicionalId;
        $this->conceptoId = '';

        $cargo = CargoAdicional::query()->find($cargoAdicionalId);

        if ($cargo && $this->partes[0]['monto'] === '') {
            $this->partes[0]['monto'] = (string) $cargo->saldoPendiente();
        }
    }

    public function limpiarCargoAdicional(): void
    {
        $this->cargoAdicionalId = null;
    }

    /**
     * Si el concepto elegido es Mensualidad y el estudiante ya tiene una
     * cuota con un pago parcial, precarga el monto de la primera parte con
     * el saldo real que falta cobrar (ej. si ya pagó S/40 de una cuota de
     * S/80, deja S/40 en vez de S/80) en vez de que Tesorería tenga que
     * calcularlo a mano. Solo toca el campo si sigue vacío, para no pisar
     * un monto que la persona ya haya escrito.
     */
    private function autocompletarMontoConSaldo(CobranzaService $cobranza): void
    {
        if (! $this->estudianteSeleccionadoId || $this->partes[0]['monto'] !== '') {
            return;
        }

        $concepto = ConceptoPago::query()->find($this->conceptoId);
        if ($concepto?->tipo !== TipoConceptoEnum::MENSUALIDAD) {
            return;
        }

        $estudiante = Estudiante::query()->find($this->estudianteSeleccionadoId);
        $cuota = $estudiante ? $cobranza->cuotaPendienteMasProxima($estudiante) : null;

        if ($cuota && $cuota->saldoPendiente() > 0.0) {
            $this->partes[0]['monto'] = (string) $cuota->saldoPendiente();
        }
    }

    public function cobrosSeleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->cobrosEstudianteId = $estudianteId;
        $this->cobrosEstudianteNombre = $nombre;
        $this->cobrosTerminoBusqueda = '';
    }

    /**
     * El filtro grupal es en cascada: Grupo primero, luego Grado, luego
     * Curso. Cambiar un nivel invalida los que dependen de él.
     */
    public function updatedCobrosCicloId(): void
    {
        $this->cobrosGradoId = '';
        $this->cobrosCursoId = '';
    }

    public function updatedCobrosGradoId(): void
    {
        $this->cobrosCursoId = '';
    }

    public function agregarParte(): void
    {
        $this->partes[] = ['monto' => '', 'metodo' => '', 'nota' => ''];
    }

    public function quitarParte(int $indice): void
    {
        if (count($this->partes) <= 1) {
            return;
        }

        unset($this->partes[$indice]);
        $this->partes = array_values($this->partes);
    }

    public function registrarPago(PagoService $service, CobranzaService $cobranza): void
    {
        abort_unless(Auth::user()->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']), 403);

        $reglas = [
            'estudianteSeleccionadoId' => 'required|integer',
            'conceptoId' => 'required|integer|exists:conceptos_pago,id',
            'partes' => 'required|array|min:1',
            'partes.*.monto' => 'required|numeric|min:0.01',
            'partes.*.metodo' => 'required|string|in:'.implode(',', array_column(MetodoPagoEnum::seleccionables(), 'value')),
            'partes.*.nota' => 'nullable|string|max:40',
            'comprobante' => 'nullable|file|max:5120',
            'fechaPago' => 'required|date|before_or_equal:today',
        ];

        // Cobrar un cargo adicional es excluyente con elegir un concepto
        // del catálogo -- ver seleccionarCargoAdicional().
        if ($this->cargoAdicionalId) {
            unset($reglas['conceptoId']);
        }

        $this->validate($reglas);

        $estudiante = Estudiante::query()->findOrFail($this->estudianteSeleccionadoId);

        if ($this->cargoAdicionalId) {
            $cargoAdicional = CargoAdicional::query()->findOrFail($this->cargoAdicionalId);
            // Concepto_id sigue siendo obligatorio en todo Pago -- para uno
            // que cobra un cargo adicional se ancla al concepto catálogo
            // "Otro" (mismo patrón de ancla ya usado en mi-cuenta.blade.php
            // para Mensualidad); el nombre que de verdad se muestra sale de
            // Pago::nombreConcepto(), no de este ancla.
            $concepto = ConceptoPago::query()->where('tipo', TipoConceptoEnum::OTRO)->first()
                ?? ConceptoPago::query()->firstOrFail();
            $cuota = null;
        } else {
            $cargoAdicional = null;
            $concepto = ConceptoPago::query()->findOrFail($this->conceptoId);

            if ($concepto->tipo === TipoConceptoEnum::OTRO && trim($this->detalle) === '') {
                $this->addError('detalle', 'Escribe el concepto específico de este cobro.');

                return;
            }

            // Solo Mensualidad tiene una Cuota real que vincular (ver
            // CobranzaService::cuotaPendienteMasProxima()) -- el resto de
            // conceptos (Matrícula, Certificado, Otro...) no forman parte de
            // un plan de cuotas, así que el pago queda sin Cuota como antes.
            $cuota = $concepto->tipo === TipoConceptoEnum::MENSUALIDAD
                ? $cobranza->cuotaPendienteMasProxima($estudiante)
                : null;
        }

        $partes = collect($this->partes)->map(fn (array $parte) => [
            'monto' => (float) $parte['monto'],
            'metodo' => $parte['metodo'],
            'nota' => $parte['nota'] !== '' ? $parte['nota'] : null,
        ])->all();

        $service->registrar($estudiante, $concepto, $partes, $cuota, $this->comprobante, Auth::id(), $this->detalle ?: null, $this->observacion ?: null, $this->fechaPago, $cargoAdicional);

        $this->reset(['estudianteSeleccionadoId', 'estudianteSeleccionadoNombre', 'conceptoId', 'cargoAdicionalId', 'detalle', 'observacion', 'partes', 'comprobante']);
        $this->fechaPago = now()->format('Y-m-d');
        session()->flash('status', 'Pago registrado. Queda pendiente de aprobación de Tesorería.');
    }

    public function crearPlan(int $matriculaId, PlanPagoService $service): void
    {
        abort_unless(Auth::user()->hasAnyPermission(['pagos.gestionar']), 403);

        $this->validate([
            "numeroCuotasPorMatricula.{$matriculaId}" => ['required', 'integer', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
            "montoTotalPorMatricula.{$matriculaId}" => 'required|numeric|min:1',
        ]);

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $service->crear(
            $matricula,
            NumeroCuotasEnum::from((int) $this->numeroCuotasPorMatricula[$matriculaId]),
            (float) $this->montoTotalPorMatricula[$matriculaId],
        );

        unset($this->numeroCuotasPorMatricula[$matriculaId], $this->montoTotalPorMatricula[$matriculaId]);
        session()->flash('status', 'Plan de pago creado.');
    }

    public function aprobar(int $pagoId, PagoService $service): void
    {
        Gate::authorize('pagos.aprobar');

        $serie = SerieReciboEnum::from($this->serieElegida[$pagoId] ?? SerieReciboEnum::ORIGINAL->value);

        $service->aprobar(Pago::query()->findOrFail($pagoId), Auth::id(), $serie);

        unset($this->serieElegida[$pagoId]);
        session()->flash('status', "Pago aprobado y recibo generado (serie {$serie->value}).");
    }

    public function rechazar(int $pagoId, PagoService $service): void
    {
        Gate::authorize('pagos.rechazar');

        $this->validate(['motivoRechazo.'.$pagoId => 'required|string|max:255']);

        $service->rechazar(Pago::query()->findOrFail($pagoId), Auth::id(), $this->motivoRechazo[$pagoId]);

        unset($this->motivoRechazo[$pagoId]);
        session()->flash('status', 'Pago rechazado.');
    }

    public function with(PagoService $pagos, ConceptoPagoService $conceptos, CobranzaService $cobranza): array
    {
        $user = Auth::user();
        $puedeAprobar = $user->hasPermissionTo('pagos.aprobar');
        $puedeRegistrar = $user->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']);
        $puedeVerHistorial = $user->hasAnyPermission(['pagos.ver', 'pagos.gestionar']);
        $puedeVerCobros = $puedeVerHistorial;

        $resultadosBusqueda = collect();
        if ($this->terminoBusqueda !== '' && $puedeRegistrar) {
            $resultadosBusqueda = Estudiante::query()
                ->where(function ($query) {
                    $termino = $this->terminoBusqueda;
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $matriculasSinPlan = collect();
        if ($puedeRegistrar) {
            $matriculasSinPlan = Matricula::query()
                ->where('estado', 'aprobada')
                ->whereNotIn('id', PlanPago::query()->pluck('matricula_id'))
                ->with(['estudiante', 'ciclo', 'grado'])
                ->get();

            foreach ($matriculasSinPlan as $matricula) {
                $this->numeroCuotasPorMatricula[$matricula->id] ??= '6';
                $this->montoTotalPorMatricula[$matricula->id] ??= '';
            }
        }

        $colaAprobacion = $puedeAprobar ? $pagos->pendientesDeAprobacion() : collect();
        foreach ($colaAprobacion as $pago) {
            $this->serieElegida[$pago->id] ??= SerieReciboEnum::ORIGINAL->value;
        }

        $historial = $puedeVerHistorial
            ? $pagos->todos()->when($this->filtroEstado, fn ($coleccion) => $coleccion->where('estado', $this->filtroEstado))
            : collect();

        $conceptosActivos = $conceptos->activos();

        // Vista previa de a qué Cuota quedaría vinculado el pago si se
        // registrara ahora mismo (ver registrarPago(), que hace la misma
        // detección al guardar) -- solo tiene sentido una vez elegidos
        // estudiante y un concepto de tipo Mensualidad.
        $cuotaDetectada = null;
        if ($puedeRegistrar && $this->estudianteSeleccionadoId
            && $conceptosActivos->firstWhere('id', (int) $this->conceptoId)?->tipo === TipoConceptoEnum::MENSUALIDAD) {
            $estudianteParaCuota = Estudiante::query()->find($this->estudianteSeleccionadoId);
            $cuotaDetectada = $estudianteParaCuota ? $cobranza->cuotaPendienteMasProxima($estudianteParaCuota) : null;
        }

        // Cargos adicionales puntuales pendientes del estudiante elegido --
        // a diferencia de $cuotaDetectada, no depende del concepto elegido:
        // se listan como una opción de cobro aparte del catálogo (ver
        // seleccionarCargoAdicional()).
        $cargosAdicionalesPendientes = ($puedeRegistrar && $this->estudianteSeleccionadoId)
            ? CargoAdicional::query()
                ->where('estudiante_id', $this->estudianteSeleccionadoId)
                ->where('estado', EstadoCuotaEnum::PENDIENTE)
                ->get()
            : collect();

        $cobrosResultadosBusqueda = collect();
        if ($puedeVerCobros && $this->cobrosTerminoBusqueda !== '') {
            $cobrosResultadosBusqueda = Estudiante::query()
                ->where(function ($query) {
                    $termino = $this->cobrosTerminoBusqueda;
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $cobrosDeudaIndividual = null;
        if ($puedeVerCobros && $this->cobrosEstudianteId) {
            $cobrosEstudiante = Estudiante::query()->find($this->cobrosEstudianteId);
            $cobrosDeudaIndividual = $cobrosEstudiante ? $cobranza->deudaDeEstudiante($cobrosEstudiante) : null;
        }

        $cobrosReporteGrupal = ['columnas' => [], 'filas' => []];
        if ($puedeVerCobros && $this->cobrosConceptoIds !== []) {
            $cobrosReporteGrupal = $cobranza->deudoresPorConceptos(
                array_map('intval', $this->cobrosConceptoIds),
                $this->cobrosCicloId !== '' ? (int) $this->cobrosCicloId : null,
                $this->cobrosGradoId !== '' ? (int) $this->cobrosGradoId : null,
                $this->cobrosCursoId !== '' ? (int) $this->cobrosCursoId : null,
                $this->cobrosFranja !== '' ? $this->cobrosFranja : null,
            );
        }

        $cobrosCursos = ($puedeVerCobros && $this->cobrosGradoId !== '')
            ? Curso::query()->where('grado_id', (int) $this->cobrosGradoId)->where('activo', true)->orderBy('nombre')->get()
            : collect();

        return [
            'puedeAprobar' => $puedeAprobar,
            'puedeRegistrar' => $puedeRegistrar,
            'puedeVerHistorial' => $puedeVerHistorial,
            'puedeVerCobros' => $puedeVerCobros,
            'colaAprobacion' => $colaAprobacion,
            'historial' => $historial,
            'resultadosBusqueda' => $resultadosBusqueda,
            'matriculasSinPlan' => $matriculasSinPlan,
            'conceptosActivos' => $conceptosActivos,
            'mostrarDetalleLibre' => $conceptosActivos->firstWhere('id', (int) $this->conceptoId)?->tipo === TipoConceptoEnum::OTRO,
            'cuotaDetectada' => $cuotaDetectada,
            'cargosAdicionalesPendientes' => $cargosAdicionalesPendientes,
            'series' => SerieReciboEnum::cases(),
            'numerosCuotas' => NumeroCuotasEnum::cases(),
            'metodosPago' => MetodoPagoEnum::seleccionables(),
            'totalPartes' => collect($this->partes)->sum(fn (array $parte) => (float) ($parte['monto'] ?: 0)),
            'cobrosResultadosBusqueda' => $cobrosResultadosBusqueda,
            'cobrosDeudaIndividual' => $cobrosDeudaIndividual,
            'cobrosReporteGrupal' => $cobrosReporteGrupal,
            'cobrosCiclos' => $puedeVerCobros ? Ciclo::query()->orderByDesc('fecha_inicio')->get() : collect(),
            'cobrosGrados' => $puedeVerCobros ? Grado::query()->where('activo', true)->orderBy('orden')->get() : collect(),
            'cobrosCursos' => $cobrosCursos,
            'cobrosFranjas' => collect(FranjaHorarioEnum::cases())->map(fn ($franja) => ['value' => $franja->value, 'label' => $franja->label()]),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Pagos y Cobranza</h1>
        <p class="mt-1 text-sm text-ink-dim">Cola de aprobación, registro de pagos y planes de cuotas.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        @if ($puedeAprobar)
            <button wire:click="$set('tab', 'aprobacion')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'aprobacion', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'aprobacion'])>
                Cola de aprobación
                @if ($colaAprobacion->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $colaAprobacion->count() }}</span>
                @endif
            </button>
        @endif
        @if ($puedeVerCobros)
            <button wire:click="$set('tab', 'cobros')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'cobros', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'cobros'])>
                Cobros
            </button>
        @endif
        @if ($puedeRegistrar)
            <button wire:click="$set('tab', 'registrar')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'registrar', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'registrar'])>
                Registrar pago
            </button>
            <button wire:click="$set('tab', 'planes')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'planes', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'planes'])>
                Planes de pago
                @if ($matriculasSinPlan->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $matriculasSinPlan->count() }}</span>
                @endif
            </button>
        @endif
        @if ($puedeVerHistorial)
            <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
                Historial
            </button>
        @endif
    </div>

    {{-- Cola de aprobación --}}
    @if ($tab === 'aprobacion' && $puedeAprobar)
        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
            @forelse ($colaAprobacion as $pago)
                <div class="px-4 py-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}
                                @if ($pago->cuota)
                                    · cuota {{ $pago->cuota->numero }}
                                @endif
                                · {{ $pago->medioPagoResumen() }} · {{ $pago->fecha_pago->format('d/m/Y') }}
                            </p>
                            @if ($pago->partes->count() > 1)
                                <p class="text-xs text-ink-faint">
                                    {{ $pago->partes->map(fn ($parte) => 'S/ '.number_format((float) $parte->monto, 2).' '.$parte->metodoConNota())->implode(' + ') }}
                                </p>
                            @endif
                            @if ($pago->getFirstMedia('comprobante'))
                                <a href="{{ $pago->getFirstMediaUrl('comprobante') }}" target="_blank" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">Ver comprobante</a>
                            @endif
                        </div>
                        <p class="font-display text-lg text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <select
                            wire:model="serieElegida.{{ $pago->id }}"
                            class="rounded-md border-border bg-surface text-xs text-ink focus:border-accent focus:ring-accent"
                        >
                            @foreach ($series as $serieOpcion)
                                <option value="{{ $serieOpcion->value }}">Serie {{ $serieOpcion->value }} — {{ $serieOpcion->titulo() }}</option>
                            @endforeach
                        </select>
                        <x-secondary-button type="button" x-on:click="$store.confirm.preguntar('¿Aprobar este pago? Se generará el recibo automáticamente.', () => $wire.aprobar({{ $pago->id }}), { etiquetaConfirmar: 'Aprobar' })">
                            Aprobar
                        </x-secondary-button>
                        <input
                            type="text"
                            wire:model="motivoRechazo.{{ $pago->id }}"
                            placeholder="Motivo de rechazo…"
                            class="w-52 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                        >
                        <button type="button" wire:click="rechazar({{ $pago->id }})" class="text-xs font-medium text-danger hover:underline">Rechazar</button>
                        <x-input-error :messages="$errors->get('motivoRechazo.'.$pago->id)" class="mt-0" />
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay pagos pendientes de aprobación.</p>
            @endforelse
        </div>
    @endif

    {{-- Registrar pago --}}
    @if ($tab === 'registrar' && $puedeRegistrar)
        <div class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
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

            @if ($estudianteSeleccionadoId && $cargosAdicionalesPendientes->isNotEmpty())
                <div>
                    <x-input-label value="Cargo adicional pendiente" />
                    @if ($cargoAdicionalId)
                        @php $cargoElegido = $cargosAdicionalesPendientes->firstWhere('id', $cargoAdicionalId); @endphp
                        <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                            Cobrar: {{ $cargoElegido?->concepto }} — saldo S/ {{ number_format($cargoElegido?->saldoPendiente() ?? 0, 2) }}
                            <button type="button" wire:click="limpiarCargoAdicional" class="text-xs underline">Cambiar</button>
                        </div>
                    @else
                        <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                            @foreach ($cargosAdicionalesPendientes as $cargo)
                                <button
                                    type="button"
                                    wire:click="seleccionarCargoAdicional({{ $cargo->id }})"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                >
                                    Cobrar: {{ $cargo->concepto }} <span class="text-ink-faint">· saldo S/ {{ number_format($cargo->saldoPendiente(), 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-ink-faint">O elige un concepto del catálogo abajo si no es ninguno de estos.</p>
                    @endif
                </div>
            @endif

            @if (! $cargoAdicionalId)
                <div>
                    <x-input-label for="conceptoId" value="Concepto" />
                    <x-select-input
                        wire:model.live="conceptoId"
                        id="conceptoId"
                        class="mt-1 block w-full"
                        :options="collect($conceptosActivos)->mapWithKeys(fn ($concepto) => [$concepto->id => $concepto->nombre.' (S/ '.number_format((float) $concepto->monto_base, 2).')'])"
                    />
                    <x-input-error :messages="$errors->get('conceptoId')" class="mt-1" />
                </div>
            @endif

            @if ($mostrarDetalleLibre)
                <div>
                    <x-input-label for="detalle" value="Detalle del concepto" />
                    <x-text-input wire:model="detalle" id="detalle" class="mt-1 block w-full" placeholder="Ej. duplicado de constancia, exoneración…" />
                    <x-input-error :messages="$errors->get('detalle')" class="mt-1" />
                </div>
            @endif

            @if ($cuotaDetectada)
                <div class="rounded-md border border-accent/30 bg-accent-soft px-3 py-2 text-xs text-ink">
                    <p>
                        <span class="font-semibold text-accent">Cuota N.° {{ $cuotaDetectada->numero }}</span>
                        de {{ $cuotaDetectada->planPago->numero_cuotas }}
                        · Programa de estudio {{ $cuotaDetectada->planPago->matricula->ciclo->nombre }}
                        · vence {{ $cuotaDetectada->fecha_vencimiento->format('d/m/Y') }}
                    </p>
                    <p class="mt-1 text-ink-dim">
                        @if ($cuotaDetectada->montoPagado() > 0)
                            Saldo pendiente: S/ {{ number_format($cuotaDetectada->saldoPendiente(), 2) }}
                            de S/ {{ number_format((float) $cuotaDetectada->monto, 2) }} —
                        @else
                            Monto de la cuota: S/ {{ number_format((float) $cuotaDetectada->monto, 2) }} —
                        @endif
                        @if ($totalPartes <= 0)
                            se vinculará automáticamente a este pago.
                        @elseif ($totalPartes >= $cuotaDetectada->saldoPendiente())
                            pago <span class="font-semibold text-ok">completo</span>.
                        @else
                            pago <span class="font-semibold text-warn">parcial</span>.
                        @endif
                    </p>
                </div>
            @endif

            <div>
                <x-input-label for="fechaPago" value="Fecha de pago" />
                <x-date-input wire:model="fechaPago" id="fechaPago" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('fechaPago')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="observacion" value="Observación (opcional)" />
                <x-text-input wire:model="observacion" id="observacion" class="mt-1 block w-full" placeholder="Nota que se imprime en el recibo…" />
                <x-input-error :messages="$errors->get('observacion')" class="mt-1" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <x-input-label value="Monto y método" />
                    {{--
                        wire:loading.attr="disabled" (sin wire:target, así
                        que aplica mientras CUALQUIER petición del
                        componente esté en curso) evita que se dispare esta
                        acción mientras otra sigue en vuelo -- elegir un
                        select propio (x-select-input, que llama a $wire.set
                        directo desde Alpine) y hacer clic aquí casi
                        seguido puede mandar dos peticiones que no se
                        serializan entre sí, y la que responde después
                        "revierte" visualmente a la parte recién agregada
                        aunque sí quedó guardada en el servidor.
                    --}}
                    <button type="button" wire:click="agregarParte" wire:loading.attr="disabled" class="text-xs font-medium text-accent hover:underline disabled:cursor-not-allowed disabled:opacity-50">+ Agregar parte</button>
                </div>
                <p class="mt-1 text-xs text-ink-faint">Si paga con más de un medio (ej. una parte en efectivo y otra por Yape), agrega una parte por cada uno — queda como un solo registro.</p>

                <div class="mt-2 space-y-3" wire:key="partes-contenedor-{{ count($partes) }}">
                    @foreach ($partes as $indice => $parte)
                        <div wire:key="parte-{{ $indice }}">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <x-text-input wire:model.live.debounce.400ms="partes.{{ $indice }}.monto" type="number" step="0.01" min="0" placeholder="Monto (S/)" class="block w-full" />
                                    <x-input-error :messages="$errors->get('partes.'.$indice.'.monto')" class="mt-1" />
                                </div>
                                <div class="flex-1">
                                    <x-select-input
                                        wire:model.live="partes.{{ $indice }}.metodo"
                                        placeholder="Método…"
                                        class="block w-full"
                                        :options="collect($metodosPago)->mapWithKeys(fn ($metodoOpcion) => [$metodoOpcion->value => $metodoOpcion->label()])"
                                    />
                                    <x-input-error :messages="$errors->get('partes.'.$indice.'.metodo')" class="mt-1" />
                                </div>
                                @if (count($partes) > 1)
                                    <button type="button" wire:click="quitarParte({{ $indice }})" wire:loading.attr="disabled" class="mt-2 shrink-0 text-xs text-danger hover:underline disabled:cursor-not-allowed disabled:opacity-50">Quitar</button>
                                @endif
                            </div>
                            {{--
                                Nota opcional para dejar constancia de quién
                                recibió el pago cuando no fue por la cuenta
                                institucional (ej. "Walter", "Director") --
                                se pega al método al mostrarlo ("Yape
                                Walter"), ver PagoParte::metodoConNota(). El
                                método en sí sigue siendo el mismo enum
                                cerrado del que dependen los reportes.
                            --}}
                            <x-text-input wire:model.blur="partes.{{ $indice }}.nota" placeholder="De quién es la cuenta (opcional) — ej. Walter, Director…" class="mt-1 block w-full text-xs" />
                            <x-input-error :messages="$errors->get('partes.'.$indice.'.nota')" class="mt-1" />
                        </div>
                    @endforeach
                </div>

                <x-input-error :messages="$errors->get('partes')" class="mt-1" />
                <p class="mt-2 text-right text-sm font-display text-ink">Total: S/ {{ number_format($totalPartes, 2) }}</p>
            </div>

            <div>
                <x-input-label for="comprobante" value="Comprobante (opcional)" />
                <input wire:model="comprobante" id="comprobante" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                <x-input-error :messages="$errors->get('comprobante')" class="mt-1" />
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="registrarPago">Registrar pago</x-primary-button>
            </div>
        </div>
    @endif

    {{-- Planes de pago --}}
    @if ($tab === 'planes' && $puedeRegistrar)
        <div class="space-y-4">
            <p class="text-sm text-ink-dim">Matrículas aprobadas sin un plan de pago asignado este ciclo.</p>
            <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                @forelse ($matriculasSinPlan as $matricula)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $matricula->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">{{ $matricula->grado->nombre }} · {{ $matricula->ciclo->nombre }}</p>
                        </div>
                        <form wire:submit="crearPlan({{ $matricula->id }})" class="flex items-start gap-2">
                            <div>
                                <x-select-input
                                    wire:model="numeroCuotasPorMatricula.{{ $matricula->id }}"
                                    class="text-xs"
                                    :options="collect($numerosCuotas)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])"
                                />
                            </div>
                            <div>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    placeholder="Monto total"
                                    wire:model="montoTotalPorMatricula.{{ $matricula->id }}"
                                    class="w-28 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                >
                                <x-input-error :messages="$errors->get('montoTotalPorMatricula.'.$matricula->id)" class="mt-1" />
                            </div>
                            <button type="submit" class="text-xs font-medium text-accent hover:underline">
                                Crear plan
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todas las matrículas aprobadas ya tienen un plan de pago.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial' && $puedeVerHistorial)
        <div class="space-y-4">
            <x-select-input
                wire:model.live="filtroEstado"
                class="w-full sm:max-w-xs"
                :options="[
                    '' => 'Todos los estados',
                    'pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'rechazado' => 'Rechazado',
                ]"
            />

            <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                @forelse ($historial as $pago)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }} · {{ $pago->fecha_pago->format('d/m/Y') }}</p>
                            @if ($pago->estado->value === 'rechazado' && $pago->motivo_rechazo)
                                <p class="text-xs text-danger">{{ $pago->motivo_rechazo }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-ok/10 text-ok' => $pago->estado->value === 'aprobado',
                                'bg-warn/10 text-warn' => $pago->estado->value === 'pendiente',
                                'bg-danger/10 text-danger' => $pago->estado->value === 'rechazado',
                            ])>{{ $pago->estado->label() }}</span>
                            <x-recibo-enlaces :recibo="$pago->recibo" />
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay pagos registrados.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Cobros --}}
    @if ($tab === 'cobros' && $puedeVerCobros)
        <div class="space-y-4">
            <div class="flex w-fit gap-1 rounded-2xl border border-border bg-surface shadow-sm p-1">
                <button type="button" wire:click="$set('cobrosModo', 'individual')" @class(['rounded-md px-3 py-1.5 text-sm font-medium transition', 'bg-accent text-white' => $cobrosModo === 'individual', 'text-ink-dim hover:text-ink' => $cobrosModo !== 'individual'])>
                    Individual
                </button>
                <button type="button" wire:click="$set('cobrosModo', 'grupal')" @class(['rounded-md px-3 py-1.5 text-sm font-medium transition', 'bg-accent text-white' => $cobrosModo === 'grupal', 'text-ink-dim hover:text-ink' => $cobrosModo !== 'grupal'])>
                    Grupal
                </button>
            </div>

            {{-- Cobros: individual --}}
            @if ($cobrosModo === 'individual')
                <div class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <div>
                        <x-input-label value="Estudiante" />
                        @if ($cobrosEstudianteId)
                            <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                                {{ $cobrosEstudianteNombre }}
                                <button type="button" wire:click="$set('cobrosEstudianteId', null)" class="text-xs underline">Cambiar</button>
                            </div>
                        @else
                            <x-text-input wire:model.live.debounce.300ms="cobrosTerminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre, apellido o DNI…" />
                            @if ($cobrosResultadosBusqueda->isNotEmpty())
                                <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                                    @foreach ($cobrosResultadosBusqueda as $estudiante)
                                        <button
                                            type="button"
                                            wire:click="cobrosSeleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                        >
                                            {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($cobrosDeudaIndividual)
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-border bg-surface shadow-sm">
                            <div class="border-b border-border px-4 py-3">
                                <h3 class="font-display text-sm text-ink">Cuotas pendientes</h3>
                            </div>
                            <div class="divide-y divide-border">
                                @forelse ($cobrosDeudaIndividual['cuotasPendientes'] as $cuota)
                                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                                        <div>
                                            <p class="text-ink">Cuota {{ $cuota->numero }} — {{ $cuota->planPago->matricula->grado->nombre ?? '—' }}</p>
                                            <p @class(['text-xs', 'text-danger' => $cuota->estaVencida(), 'text-ink-faint' => ! $cuota->estaVencida()])>
                                                {{ $cuota->estaVencida() ? 'Vencida desde' : 'Vence el' }} {{ $cuota->fecha_vencimiento->format('d/m/Y') }}
                                            </p>
                                        </div>
                                        <p class="font-display text-ink">S/ {{ number_format($cuota->saldoPendiente(), 2) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-ink-faint">Sin cuotas pendientes.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-surface shadow-sm">
                            <div class="border-b border-border px-4 py-3">
                                <h3 class="font-display text-sm text-ink">Cargos adicionales pendientes</h3>
                            </div>
                            <div class="divide-y divide-border">
                                @forelse ($cobrosDeudaIndividual['cargosAdicionalesPendientes'] as $cargo)
                                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                                        <p class="text-ink">{{ $cargo->concepto }}</p>
                                        <p class="font-display text-ink">S/ {{ number_format($cargo->saldoPendiente(), 2) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-ink-faint">Sin cargos adicionales pendientes.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-surface shadow-sm">
                            <div class="border-b border-border px-4 py-3">
                                <h3 class="font-display text-sm text-ink">Pagos pendientes o rechazados</h3>
                            </div>
                            <div class="divide-y divide-border">
                                @forelse ($cobrosDeudaIndividual['pagosPendientes'] as $pago)
                                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                                        <div>
                                            <p class="text-ink">{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</p>
                                            <p @class(['text-xs', 'text-danger' => $pago->estado->value === 'rechazado', 'text-warn' => $pago->estado->value !== 'rechazado'])>
                                                {{ $pago->estado->label() }}{{ $pago->estado->value === 'rechazado' && $pago->motivo_rechazo ? ' — '.$pago->motivo_rechazo : '' }}
                                            </p>
                                        </div>
                                        <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-ink-faint">Sin pagos pendientes ni rechazados.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-surface shadow-sm">
                            <div class="border-b border-border px-4 py-3">
                                <h3 class="font-display text-sm text-ink">Pagos ya cobrados</h3>
                            </div>
                            <div class="divide-y divide-border">
                                @forelse ($cobrosDeudaIndividual['pagosAprobados'] as $pago)
                                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                                        <div>
                                            <p class="text-ink">{{ $pago->nombreConcepto() }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}</p>
                                            <p class="text-xs text-ink-faint">
                                                {{ $pago->fecha_pago->format('d/m/Y') }} · {{ $pago->medioPagoResumen() }}
                                            </p>
                                            @if ($pago->partes->count() > 1)
                                                <p class="text-xs text-ink-faint">
                                                    {{ $pago->partes->map(fn ($parte) => 'S/ '.number_format((float) $parte->monto, 2).' '.$parte->metodoConNota())->implode(' + ') }}
                                                </p>
                                            @endif
                                            <x-recibo-enlaces :recibo="$pago->recibo" />
                                        </div>
                                        <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-ink-faint">Sin pagos cobrados todavía.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Cobros: grupal --}}
            @if ($cobrosModo === 'grupal')
                <div class="space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <x-input-label for="cobrosCicloId" value="Programa de estudio" />
                            <x-select-input
                                wire:model.live="cobrosCicloId"
                                id="cobrosCicloId"
                                class="mt-1 block w-56"
                                :options="collect($cobrosCiclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])->prepend('Todos los programas de estudio', '')"
                            />
                        </div>
                        {{--
                            wire:key fuerza a Livewire a recrear estos selects
                            cuando cambia de qué depende su lista de opciones
                            (si no, Alpine no vuelve a evaluar las opciones
                            tras el morph -- ver el mismo fix en Reportes).
                        --}}
                        <div wire:key="cobros-grado-select-{{ $cobrosCicloId }}">
                            <x-input-label for="cobrosGradoId" value="Semestre" />
                            <x-select-input
                                wire:model.live="cobrosGradoId"
                                id="cobrosGradoId"
                                class="mt-1 block w-48"
                                :disabled="$cobrosCicloId === ''"
                                :options="collect($cobrosGrados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Todos los semestres', '')"
                            />
                        </div>
                        <div wire:key="cobros-curso-select-{{ $cobrosGradoId }}">
                            <x-input-label for="cobrosCursoId" value="Curso" />
                            <x-select-input
                                wire:model.live="cobrosCursoId"
                                id="cobrosCursoId"
                                class="mt-1 block w-48"
                                :disabled="$cobrosGradoId === ''"
                                :options="collect($cobrosCursos)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre])->prepend('Todos los cursos', '')"
                            />
                        </div>
                        <div>
                            <x-input-label for="cobrosFranja" value="Horario (opcional)" />
                            <x-select-input
                                wire:model.live="cobrosFranja"
                                id="cobrosFranja"
                                class="mt-1 block w-56"
                                :options="collect($cobrosFranjas)->mapWithKeys(fn ($opcion) => [$opcion['value'] => $opcion['label']])->prepend('Todos los horarios', '')"
                            />
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Conceptos" />
                        <p class="mt-1 text-xs text-ink-faint">Elige uno o más — se listan los estudiantes que deben cualquiera de ellos.</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($conceptosActivos as $concepto)
                                <label class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm text-ink">
                                    <input type="checkbox" wire:model.live="cobrosConceptoIds" value="{{ $concepto->id }}" class="rounded border-border text-accent focus:ring-accent">
                                    {{ $concepto->nombre }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-border bg-surface shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-2">
                            <tr>
                                @foreach ($cobrosReporteGrupal['columnas'] as $columna)
                                    <th class="whitespace-nowrap px-4 py-2 font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($cobrosReporteGrupal['filas'] as $fila)
                                <tr>
                                    @foreach ($fila as $valor)
                                        <td class="whitespace-nowrap px-4 py-2 text-ink">{{ $valor }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-sm text-ink-faint">
                                        @if ($cobrosConceptoIds === [])
                                            Elige al menos un concepto para ver la lista.
                                        @else
                                            No hay estudiantes que deban los conceptos elegidos con estos filtros.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
