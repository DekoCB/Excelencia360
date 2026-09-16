<?php

use App\Models\User;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\BloqueoAcceso;
use App\Modules\Pagos\Models\CuentaBancaria;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public bool $puedeVerUsuarios = false;

    public bool $puedeVerAuditoria = false;

    public int $totalUsuarios = 0;

    public int $usuariosActivos = 0;

    public int $totalRoles = 0;

    public int $totalPermisos = 0;

    /** @var array<int, AuditLog> */
    public array $actividadReciente = [];

    public bool $esDocente = false;

    public int $misHorarios = 0;

    public int $tareasPorCalificar = 0;

    /** @var array<int, array{label: string, valor: float}> */
    public array $asistenciaPorCurso = [];

    /** @var array<int, array{label: string, valor: float}> */
    public array $distribucionNotas = [];

    public bool $esEstudianteConFicha = false;

    public bool $estoyBloqueado = false;

    public ?Cuota $proximaCuota = null;

    /** @var array<int, string> */
    public array $rendimientoMensualLabels = [];

    /** @var array<int, float> */
    public array $rendimientoMensualDatos = [];

    public int $misCursosVirtuales = 0;

    /** @var Collection<int, Tarea> */
    public Collection $misTareasLista;

    /**
     * Mes mostrado en el calendario de "Mis tareas", formato "Y-m".
     */
    public string $mesCalendarioTareas = '';

    /**
     * Día seleccionado en el calendario (formato "Y-m-d") para mostrar el
     * detalle de sus tareas debajo de la grilla; null si ninguno.
     */
    public ?string $diaCalendarioSeleccionado = null;

    /**
     * La tarea pendiente más próxima de cada curso virtual matriculado, para
     * el widget "Próximos vencimientos" — a diferencia de misTareasLista
     * (todas las tareas), aquí solo entra una por curso: la más urgente.
     *
     * @var Collection<int, Tarea>
     */
    public Collection $proximosVencimientos;

    /** @var SupportCollection<int, array<string, mixed>> */
    public SupportCollection $misCalificacionesPorCiclo;

    public ?int $miEstudianteId = null;

    public bool $esCoordinador = false;

    public int $estudiantesActivos = 0;

    public int $docentesActivos = 0;

    public int $estudiantesBloqueados = 0;

    public int $evaluacionesSinPublicar = 0;

    public int $matriculasSinPlanDePago = 0;

    /** @var array<int, array{label: string, valor: float}> */
    public array $asistenciaPorGrado = [];

    public bool $esTesoreria = false;

    public bool $esAdministrativo = false;

    public int $pagosPendientesAprobacion = 0;

    public float $ingresosDelMes = 0.0;

    public int $cuentasBancariasActivas = 0;

    /** @var array<int, string> */
    public array $ingresosSemanasLabels = [];

    /** @var array<int, float> */
    public array $ingresosSemanasDatos = [];

    /**
     * @var array<int, array{pill: string, color: string, texto: string}>
     */
    public array $notificaciones = [];

    public function mount(BloqueoAccesoService $bloqueos, CursoVirtualService $cursosVirtuales, TareaService $tareas, EvaluacionService $evaluaciones): void
    {
        $user = Auth::user();

        $this->misTareasLista = new Collection;
        $this->proximosVencimientos = new Collection;
        $this->misCalificacionesPorCiclo = collect();
        $this->mesCalendarioTareas = now()->format('Y-m');

        $this->puedeVerUsuarios = Gate::allows('usuarios.ver');
        $this->puedeVerAuditoria = Gate::allows('auditoria.ver');

        if ($this->puedeVerUsuarios) {
            $this->totalUsuarios = User::query()->count();
            $this->usuariosActivos = User::query()->where('estado', EstadoUsuarioEnum::ACTIVO)->count();
            $this->totalRoles = Role::query()->count();
            $this->totalPermisos = Permission::query()->count();
        }

        if ($this->puedeVerAuditoria) {
            $this->actividadReciente = AuditLog::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->all();
        }

        if ($user->hasRole(RolEnum::DOCENTE->value)) {
            $this->esDocente = true;
            $this->misHorarios = Horario::query()->where('docente_id', $user->id)->count();
            $this->tareasPorCalificar = Tarea::query()
                ->whereHas('cursoVirtual.horario', fn ($query) => $query->where('docente_id', $user->id))
                ->withCount(['entregas' => fn ($query) => $query->whereIn('estado', ['entregado', 'tarde'])])
                ->get()
                ->sum('entregas_count');
            $this->asistenciaPorCurso = $this->calcularAsistenciaPorCursoDelDocente($user->id);
            $this->distribucionNotas = $this->calcularDistribucionNotasDelDocente($user->id);
        }

        if (Gate::allows('aula_virtual.ver_propio') && $user->estudiante) {
            $estudiante = $user->estudiante;
            $this->esEstudianteConFicha = true;
            $this->estoyBloqueado = $bloqueos->estaBloqueado($estudiante);

            $this->proximaCuota = Cuota::query()
                ->where('estado', 'pendiente')
                ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
                ->orderBy('fecha_vencimiento')
                ->first();

            $misCursos = $cursosVirtuales->delEstudiante($estudiante);
            $this->misCursosVirtuales = $misCursos->count();

            $this->miEstudianteId = $estudiante->id;
            $this->misTareasLista = $tareas->delEstudiante($estudiante);
            $this->proximosVencimientos = $this->misTareasLista
                ->filter(fn (Tarea $tarea) => $tarea->entregas->isEmpty())
                ->groupBy('curso_virtual_id')
                ->map(fn (Collection $tareasDelCurso) => $tareasDelCurso->first())
                ->sortBy(fn (Tarea $tarea) => $tarea->fecha_limite)
                ->values();
            $this->misCalificacionesPorCiclo = $evaluaciones->resumenDelEstudiantePorCiclo($estudiante);

            [$this->rendimientoMensualLabels, $this->rendimientoMensualDatos] = $this->calcularRendimientoMensualDelEstudiante($estudiante);
        }

        if (Gate::allows('academico.gestionar')) {
            $this->esCoordinador = true;
            $this->estudiantesActivos = Estudiante::query()->where('estado', 'activo')->count();
            $this->docentesActivos = User::role(RolEnum::DOCENTE->value)->count();
            $this->estudiantesBloqueados = BloqueoAcceso::query()->where('activo', true)->count();
            $this->evaluacionesSinPublicar = Evaluacion::query()->where('estado', 'borrador')->count();
            $this->matriculasSinPlanDePago = Matricula::query()
                ->where('estado', 'aprobada')
                ->whereNotIn('id', PlanPago::query()->pluck('matricula_id'))
                ->count();
            $this->asistenciaPorGrado = $this->calcularAsistenciaPorGrado();
        }

        if (Gate::allows('pagos.aprobar')) {
            $this->esTesoreria = true;
            $this->pagosPendientesAprobacion = Pago::query()->where('estado', 'pendiente')->count();
            $this->ingresosDelMes = (float) Pago::query()
                ->where('estado', 'aprobado')
                ->whereMonth('fecha_aprobacion', now()->month)
                ->whereYear('fecha_aprobacion', now()->year)
                ->sum('monto');
            $this->cuentasBancariasActivas = CuentaBancaria::query()->where('activa', true)->count();
        }

        $this->esAdministrativo = Gate::allows('pagos.registrar');

        if ($this->esCoordinador || $this->esTesoreria || $this->esAdministrativo) {
            [$this->ingresosSemanasLabels, $this->ingresosSemanasDatos] = $this->calcularIngresosPorSemana();
        }

        $this->notificaciones = $this->construirNotificaciones();
    }

    public function mesCalendarioTareasAnterior(): void
    {
        $this->mesCalendarioTareas = Carbon::createFromFormat('Y-m-d', $this->mesCalendarioTareas.'-01')
            ->subMonthNoOverflow()
            ->format('Y-m');
        $this->diaCalendarioSeleccionado = null;
    }

    public function mesCalendarioTareasSiguiente(): void
    {
        $this->mesCalendarioTareas = Carbon::createFromFormat('Y-m-d', $this->mesCalendarioTareas.'-01')
            ->addMonthNoOverflow()
            ->format('Y-m');
        $this->diaCalendarioSeleccionado = null;
    }

    public function seleccionarDiaCalendarioTareas(string $fecha): void
    {
        $this->diaCalendarioSeleccionado = $this->diaCalendarioSeleccionado === $fecha ? null : $fecha;
    }

    public function nombreMesCalendarioTareas(): string
    {
        return ucfirst(Carbon::createFromFormat('Y-m-d', $this->mesCalendarioTareas.'-01')->translatedFormat('F Y'));
    }

    /**
     * La grilla de semanas del mes mostrado, cada día con las tareas cuya
     * fecha_limite cae ahí — se recalcula en cada render a partir de
     * misTareasLista (ya cargada una sola vez en mount), así que cambiar de
     * mes o seleccionar un día no dispara consultas nuevas.
     *
     * @return array<int, array<int, array{fecha: string, numero: int, esMesActual: bool, esHoy: bool, tareas: Collection<int, Tarea>}>>
     */
    public function calendarioTareasSemanas(): array
    {
        $mes = Carbon::createFromFormat('Y-m-d', $this->mesCalendarioTareas.'-01')->startOfMonth();
        $inicioGrilla = $mes->copy()->startOfWeek(Carbon::MONDAY);
        $finGrilla = $mes->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);

        $tareasPorDia = $this->misTareasLista->groupBy(fn (Tarea $tarea) => $tarea->fecha_limite->format('Y-m-d'));

        $semanas = [];
        $semana = [];

        for ($dia = $inicioGrilla->copy(); $dia->lte($finGrilla); $dia->addDay()) {
            $clave = $dia->format('Y-m-d');

            $semana[] = [
                'fecha' => $clave,
                'numero' => $dia->day,
                'esMesActual' => $dia->month === $mes->month,
                'esHoy' => $dia->isToday(),
                'tareas' => $tareasPorDia->get($clave, new Collection),
            ];

            if ($dia->dayOfWeekIso === 7) {
                $semanas[] = $semana;
                $semana = [];
            }
        }

        return $semanas;
    }

    /**
     * @return Collection<int, Tarea>
     */
    public function tareasDelDiaSeleccionado(): Collection
    {
        if (! $this->diaCalendarioSeleccionado) {
            return new Collection;
        }

        return $this->misTareasLista
            ->filter(fn (Tarea $tarea) => $tarea->fecha_limite->format('Y-m-d') === $this->diaCalendarioSeleccionado)
            ->values();
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>}
     */
    private function calcularIngresosPorSemana(): array
    {
        $labels = [];
        $datos = [];

        for ($semanasAtras = 7; $semanasAtras >= 0; $semanasAtras--) {
            $inicio = now()->subWeeks($semanasAtras)->startOfWeek();
            $fin = now()->subWeeks($semanasAtras)->endOfWeek();

            $labels[] = $inicio->format('d/m');
            $datos[] = (float) Pago::query()
                ->where('estado', 'aprobado')
                ->whereBetween('fecha_aprobacion', [$inicio, $fin])
                ->sum('monto');
        }

        return [$labels, $datos];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>}
     */
    private function calcularRendimientoMensualDelEstudiante(Estudiante $estudiante): array
    {
        $labels = [];
        $datos = [];

        for ($mesesAtras = 5; $mesesAtras >= 0; $mesesAtras--) {
            $inicio = now()->subMonths($mesesAtras)->startOfMonth();
            $fin = now()->subMonths($mesesAtras)->endOfMonth();

            $labels[] = ucfirst($inicio->translatedFormat('M'));
            $datos[] = (float) round(
                Calificacion::query()
                    ->where('estudiante_id', $estudiante->id)
                    ->whereHas('evaluacion', function ($query) use ($inicio, $fin) {
                        $query->where('estado', EstadoEvaluacionEnum::PUBLICADA)
                            ->whereBetween('fecha', [$inicio, $fin]);
                    })
                    ->avg('nota_numerica') ?? 0,
                1
            );
        }

        return [$labels, $datos];
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularAsistenciaPorGrado(): array
    {
        return Grado::query()
            ->orderBy('orden')
            ->get()
            ->map(function (Grado $grado) {
                $registros = Asistencia::query()->whereHas('horario', fn ($query) => $query->where('grado_id', $grado->id));
                $total = $registros->count();

                if ($total === 0) {
                    return null;
                }

                $positivos = (clone $registros)->whereIn('estado', ['presente', 'justificado'])->count();

                return ['label' => $grado->nombre, 'valor' => round($positivos / $total * 100, 1)];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularAsistenciaPorCursoDelDocente(int $docenteId): array
    {
        return Horario::query()
            ->where('docente_id', $docenteId)
            ->with('curso')
            ->get()
            ->groupBy('curso_id')
            ->map(function ($horariosDelCurso) {
                $registros = Asistencia::query()->whereIn('horario_id', $horariosDelCurso->pluck('id'));
                $total = $registros->count();

                if ($total === 0) {
                    return null;
                }

                $positivos = (clone $registros)->whereIn('estado', ['presente', 'justificado'])->count();

                return ['label' => $horariosDelCurso->first()->curso->nombre, 'valor' => round($positivos / $total * 100, 1)];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularDistribucionNotasDelDocente(int $docenteId): array
    {
        $conteos = ['AD' => 0, 'A' => 0, 'B' => 0, 'C' => 0];

        Calificacion::query()
            ->whereHas('evaluacion.horario', fn ($query) => $query->where('docente_id', $docenteId))
            ->pluck('nota_numerica')
            ->each(function ($notaNumerica) use (&$conteos) {
                $conteos[NotaLetraEnum::desde((float) $notaNumerica)->value]++;
            });

        return collect($conteos)
            ->map(fn ($valor, $label) => ['label' => $label, 'valor' => $valor])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{pill: string, color: string, texto: string}>
     */
    private function construirNotificaciones(): array
    {
        $notificaciones = [];

        if ($this->esCoordinador) {
            if ($this->estudiantesBloqueados > 0) {
                $notificaciones[] = ['pill' => 'deuda', 'color' => 'danger', 'texto' => "{$this->estudiantesBloqueados} estudiantes bloqueados por deuda"];
            }

            if ($this->matriculasSinPlanDePago > 0) {
                $notificaciones[] = ['pill' => 'matrícula', 'color' => 'info', 'texto' => "{$this->matriculasSinPlanDePago} matrículas sin plan de pago"];
            }

            if ($this->evaluacionesSinPublicar > 0) {
                $notificaciones[] = ['pill' => 'evaluación', 'color' => 'warn', 'texto' => "{$this->evaluacionesSinPublicar} evaluaciones sin publicar"];
            }
        }

        if ($this->esTesoreria && $this->pagosPendientesAprobacion > 0) {
            $notificaciones[] = ['pill' => 'pago', 'color' => 'warn', 'texto' => "{$this->pagosPendientesAprobacion} pagos por aprobar"];
        }

        if ($this->esDocente && $this->tareasPorCalificar > 0) {
            $notificaciones[] = ['pill' => 'tarea', 'color' => 'warn', 'texto' => "{$this->tareasPorCalificar} entregas por calificar"];
        }

        return $notificaciones;
    }
}; ?>

<div>
    <div class="space-y-6">
        <x-dashboard.hero-banner :nombre="auth()->user()->name" />

        @if ($esTesoreria)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <x-dashboard.stat-card
                    href="{{ route('pagos.index') }}"
                    label="Pagos por aprobar"
                    :value="$pagosPendientesAprobacion"
                    icon="banknotes"
                    :color="$pagosPendientesAprobacion > 0 ? 'warn' : 'accent'"
                />
                <x-dashboard.stat-card
                    label="Ingresos aprobados este mes"
                    value="S/ {{ number_format($ingresosDelMes, 2) }}"
                    icon="arrow-trending-up"
                    color="ok"
                />
                <x-dashboard.stat-card
                    href="{{ route('pagos.cuentas-bancarias') }}"
                    label="Cuentas bancarias activas"
                    :value="$cuentasBancariasActivas"
                    icon="building-library"
                    color="accent"
                />
            </div>
        @endif

        @if ($esCoordinador)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-dashboard.stat-card label="Estudiantes activos" :value="$estudiantesActivos" icon="user-group" color="accent" />
                <x-dashboard.stat-card label="Docentes" :value="$docentesActivos" icon="academic-cap" color="accent" />
                <x-dashboard.stat-card
                    href="{{ route('aula-virtual.index') }}"
                    label="Evaluaciones sin publicar"
                    :value="$evaluacionesSinPublicar"
                    icon="clipboard-document-list"
                    :color="$evaluacionesSinPublicar > 0 ? 'warn' : 'accent'"
                />
                <x-dashboard.stat-card
                    href="{{ route('pagos.index') }}"
                    label="Estudiantes bloqueados por deuda"
                    :value="$estudiantesBloqueados"
                    icon="lock-closed"
                    :color="$estudiantesBloqueados > 0 ? 'danger' : 'accent'"
                />
            </div>

        @endif

        @if ($esCoordinador || $esTesoreria || $esAdministrativo)
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm lg:col-span-2">
                    <h2 class="mb-3 text-sm font-semibold text-ink">Ingresos aprobados — últimas 8 semanas</h2>
                    <x-chart-canvas
                        type="line"
                        :labels="$ingresosSemanasLabels"
                        :data="$ingresosSemanasDatos"
                        label="Ingresos (S/)"
                    />
                </div>

                <div class="rounded-2xl border border-border bg-surface shadow-sm">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-ink">Notificaciones</h2>
                    </div>
                    <div class="divide-y divide-border">
                        @forelse ($notificaciones as $notificacion)
                            @php
                                $estiloPill = match ($notificacion['color']) {
                                    'danger' => 'bg-danger/10 text-danger',
                                    'warn' => 'bg-warn/10 text-warn',
                                    'info' => 'bg-info/10 text-info',
                                    default => 'bg-ink-faint/10 text-ink-faint',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-4 py-3 text-sm">
                                <span class="shrink-0 rounded-md px-2 py-1 font-mono text-xs font-semibold uppercase tracking-wide {{ $estiloPill }}">
                                    {{ $notificacion['pill'] }}
                                </span>
                                <span class="text-ink-dim">{{ $notificacion['texto'] }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-ink-faint">Todo al día. No hay notificaciones pendientes.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if ($esCoordinador && count($asistenciaPorGrado) > 0)
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-ink">Asistencia por semestre (% presente/justificado)</h2>
                <x-chart-canvas
                    type="bar"
                    :labels="collect($asistenciaPorGrado)->pluck('label')->all()"
                    :data="collect($asistenciaPorGrado)->pluck('valor')->all()"
                    label="% Asistencia"
                    color="#5B8DEF"
                />
            </div>
        @endif

        @if ($esDocente)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-dashboard.stat-card
                    href="{{ route('asistencia.index') }}"
                    label="Mis horarios"
                    :value="$misHorarios"
                    icon="calendar-days"
                    color="accent"
                />
                <x-dashboard.stat-card
                    href="{{ route('aula-virtual.index') }}"
                    label="Tareas por calificar"
                    :value="$tareasPorCalificar"
                    icon="pencil-square"
                    :color="$tareasPorCalificar > 0 ? 'warn' : 'accent'"
                />
                <a href="{{ route('aula-virtual.index') }}" wire:navigate class="rounded-2xl border border-border bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                        <x-heroicon-o-clipboard-document-check class="h-5 w-5" />
                    </span>
                    <p class="mt-3 font-mono text-xs uppercase tracking-wide text-ink-faint">Evaluaciones</p>
                    <p class="mt-0.5 font-display text-sm text-accent">Registrar notas →</p>
                </a>
                <a href="{{ route('asistencia.index') }}" wire:navigate class="rounded-2xl border border-border bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                    </span>
                    <p class="mt-3 font-mono text-xs uppercase tracking-wide text-ink-faint">Asistencia</p>
                    <p class="mt-0.5 font-display text-sm text-accent">Tomar asistencia →</p>
                </a>
            </div>
        @endif

        @if ($esDocente && (count($asistenciaPorCurso) > 0 || array_sum(collect($distribucionNotas)->pluck('valor')->all()) > 0))
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @if (array_sum(collect($distribucionNotas)->pluck('valor')->all()) > 0)
                    <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-ink">Distribución de notas de mis evaluaciones</h2>
                        <x-chart-canvas
                            type="line"
                            :labels="collect($distribucionNotas)->pluck('label')->all()"
                            :data="collect($distribucionNotas)->pluck('valor')->all()"
                            label="Estudiantes"
                        />
                    </div>
                @endif

                @if (count($asistenciaPorCurso) > 0)
                    <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-ink">Asistencia de mis cursos (% presente/justificado)</h2>
                        <x-chart-canvas
                            type="bar"
                            :labels="collect($asistenciaPorCurso)->pluck('label')->all()"
                            :data="collect($asistenciaPorCurso)->pluck('valor')->all()"
                            label="% Asistencia"
                            color="#5B8DEF"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if ($esEstudianteConFicha)
            @if ($estoyBloqueado)
                <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                    Tienes cuotas vencidas sin pagar y tu libreta de notas no está disponible.
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Regulariza tu deuda aquí →</a>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <a href="{{ route('asistencia.marcar') }}" wire:navigate class="rounded-2xl border border-accent/30 bg-accent-soft p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-accent hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-accent/15 text-accent">
                        <x-heroicon-o-finger-print class="h-5 w-5" />
                    </span>
                    <p class="mt-3 font-mono text-xs uppercase tracking-wide text-accent">Marcar asistencia</p>
                    <p class="mt-0.5 font-display text-sm text-accent">Con tu DNI →</p>
                </a>
                <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="rounded-2xl border border-border bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $proximaCuota ? 'bg-warn/10 text-warn' : 'bg-ok/10 text-ok' }}">
                        <x-heroicon-o-banknotes class="h-5 w-5" />
                    </span>
                    <p class="mt-3 font-mono text-xs uppercase tracking-wide text-ink-faint">Próxima cuota</p>
                    @if ($proximaCuota)
                        <p class="mt-0.5 font-display text-2xl text-ink">S/ {{ number_format((float) $proximaCuota->monto, 2) }}</p>
                        <p class="text-xs text-ink-faint">vence {{ $proximaCuota->fecha_vencimiento->format('d/m/Y') }}</p>
                    @else
                        <p class="mt-0.5 font-display text-lg text-ok">Al día</p>
                    @endif
                </a>
                <x-dashboard.stat-card
                    href="{{ route('aula-virtual.index') }}"
                    label="Mis cursos virtuales"
                    :value="$misCursosVirtuales"
                    icon="play-circle"
                    color="accent"
                />
            </div>

            @if (array_sum($rendimientoMensualDatos) > 0)
                <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-ink">Mi rendimiento — promedio de notas por mes</h2>
                    <x-chart-canvas
                        type="line"
                        :labels="$rendimientoMensualLabels"
                        :data="$rendimientoMensualDatos"
                        label="Promedio"
                    />
                </div>
            @endif

            @if ($proximosVencimientos->isNotEmpty())
                <div class="rounded-2xl border border-border bg-surface shadow-sm">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-ink">Próximos vencimientos</h2>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach ($proximosVencimientos as $tarea)
                            <a
                                href="{{ route('aula-virtual.tarea', [$tarea->cursoVirtual, $tarea]) }}"
                                wire:navigate
                                class="flex items-center justify-between gap-3 px-4 py-3 text-sm transition hover:bg-surface-2"
                            >
                                <div>
                                    <p class="font-medium text-ink">{{ $tarea->titulo }}</p>
                                    <p class="text-xs text-ink-faint">{{ $tarea->cursoVirtual->horario->curso->nombre }}</p>
                                </div>
                                <span class="shrink-0 font-mono text-xs uppercase tracking-wide {{ $tarea->fecha_limite->isPast() ? 'text-danger' : ($tarea->fecha_limite->isToday() ? 'text-warn' : 'text-ink-dim') }}">
                                    {{ $tarea->fecha_limite->isPast() ? 'Vencida' : ($tarea->fecha_limite->isToday() ? 'Vence hoy' : $tarea->fecha_limite->format('d/m')) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-stretch">
                <div class="flex flex-col rounded-2xl border border-border bg-surface p-4 shadow-sm lg:h-[30rem]">
                    <div class="mb-4 flex shrink-0 items-center justify-between">
                        <h2 class="text-sm font-semibold text-ink">Mis tareas — {{ $this->nombreMesCalendarioTareas() }}</h2>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="mesCalendarioTareasAnterior" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Mes anterior">
                                <x-heroicon-o-chevron-left class="h-4 w-4" />
                            </button>
                            <button type="button" wire:click="mesCalendarioTareasSiguiente" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Mes siguiente">
                                <x-heroicon-o-chevron-right class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="grid shrink-0 grid-cols-7 gap-1 text-center font-mono text-[10px] uppercase tracking-wide text-ink-faint">
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mié</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sáb</span>
                        <span>Dom</span>
                    </div>

                    <div class="mt-1 grid shrink-0 grid-cols-7 gap-1">
                        @foreach ($this->calendarioTareasSemanas() as $semana)
                            @foreach ($semana as $dia)
                                <button
                                    type="button"
                                    wire:click="seleccionarDiaCalendarioTareas('{{ $dia['fecha'] }}')"
                                    @class([
                                        'flex min-h-16 flex-col items-center gap-1 rounded-md border p-1.5 text-xs transition hover:bg-surface-2',
                                        'border-accent bg-accent-soft hover:bg-accent-soft' => $diaCalendarioSeleccionado === $dia['fecha'],
                                        'border-border' => $diaCalendarioSeleccionado !== $dia['fecha'],
                                        'opacity-40' => ! $dia['esMesActual'],
                                    ])
                                >
                                    <span @class(['font-medium', 'text-accent' => $dia['esHoy'], 'text-ink' => ! $dia['esHoy']])>{{ $dia['numero'] }}</span>
                                    @if ($dia['tareas']->isNotEmpty())
                                        <span class="flex flex-wrap justify-center gap-0.5">
                                            @foreach ($dia['tareas']->take(4) as $tarea)
                                                <span @class([
                                                    'h-1.5 w-1.5 rounded-full',
                                                    'bg-ok' => $tarea->entregas->isNotEmpty(),
                                                    'bg-danger' => $tarea->entregas->isEmpty() && $tarea->fecha_limite->isPast(),
                                                    'bg-warn' => $tarea->entregas->isEmpty() && ! $tarea->fecha_limite->isPast(),
                                                ])></span>
                                            @endforeach
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        @endforeach
                    </div>

                    @if ($diaCalendarioSeleccionado)
                        <div class="mt-4 min-h-0 flex-1 overflow-y-auto border-t border-border pt-4">
                            <h3 class="mb-2 font-mono text-xs uppercase tracking-wide text-ink-faint">
                                Tareas del {{ Carbon::parse($diaCalendarioSeleccionado)->format('d/m/Y') }}
                            </h3>
                            @if ($this->tareasDelDiaSeleccionado()->isNotEmpty())
                                <x-aula-virtual.lista-tareas :tareas="$this->tareasDelDiaSeleccionado()" />
                            @else
                                <p class="text-sm text-ink-faint">No tienes tareas con vencimiento este día.</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex flex-col rounded-2xl border border-border bg-surface p-4 shadow-sm lg:h-[30rem]">
                    <h2 class="mb-4 shrink-0 text-sm font-semibold text-ink">Mis evaluaciones</h2>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <x-evaluaciones.lista-calificaciones :por-ciclo="$misCalificacionesPorCiclo" :mi-estudiante-id="$miEstudianteId" />
                    </div>
                </div>
            </div>
        @endif

        @if ($puedeVerUsuarios)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-dashboard.stat-card label="Usuarios totales" :value="$totalUsuarios" icon="users" color="accent" />
                <x-dashboard.stat-card label="Usuarios activos" :value="$usuariosActivos" icon="check-circle" color="ok" />
                <x-dashboard.stat-card label="Roles configurados" :value="$totalRoles" icon="shield-check" color="accent" />
                <x-dashboard.stat-card label="Permisos totales" :value="$totalPermisos" icon="key" color="accent" />
            </div>
        @endif

        @if ($puedeVerAuditoria)
            <div class="rounded-2xl border border-border bg-surface shadow-sm">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-ink">Actividad reciente</h2>
                </div>
                <div class="divide-y divide-border">
                    @forelse ($actividadReciente as $entrada)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <span class="font-medium text-ink">{{ $entrada->user?->name ?? 'Sistema' }}</span>
                                <span class="text-ink-dim">
                                    {{ match ($entrada->event) {
                                        'created' => 'creó',
                                        'updated' => 'actualizó',
                                        'deleted' => 'eliminó',
                                        default => $entrada->event,
                                    } }}
                                </span>
                                <span class="font-mono text-ink-faint">{{ class_basename($entrada->auditable_type) }} #{{ $entrada->auditable_id }}</span>
                            </div>
                            <span class="text-xs text-ink-faint">{{ $entrada->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-ink-faint">Todavía no hay actividad registrada.</p>
                    @endforelse
                </div>
                @if (count($actividadReciente) > 0)
                    <div class="border-t border-border px-4 py-3">
                        <a href="{{ route('auditoria.index') }}" wire:navigate class="text-sm font-medium text-accent hover:underline">
                            Ver historial completo →
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @unless ($puedeVerUsuarios || $puedeVerAuditoria || $esDocente || $esEstudianteConFicha || $esCoordinador || $esTesoreria || $esAdministrativo)
            <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                <h2 class="font-display text-lg text-ink">Bienvenido a {{ config('institucion.nombre_corto') }}</h2>
                <p class="mt-2 max-w-prose text-sm text-ink-dim">
                    Tu panel se irá completando a medida que se habiliten los módulos correspondientes a tu rol.
                </p>
            </div>
        @endunless
    </div>
</div>
