<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\PeriodoMatricula;
use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Dos modalidades de ciclo (ver ModalidadCicloEnum): las 4 ventanas de
 * admisión rotativas del año (Grupo 1 a 4, 6 meses cada una) y SIAGIE
 * anual (un ciclo independiente que corre el año escolar completo, sin
 * Grupo asociado). Aplica la "doble validación" del roadmap: las fechas
 * del propio ciclo deben ser coherentes con su modalidad/tipo, y las
 * fechas de matrícula deben ser coherentes con las del ciclo.
 */
class CicloService
{
    /**
     * Cuántos días antes del inicio del ciclo se permite abrir matrícula.
     */
    private const DIAS_MATRICULA_ANTICIPADA = 30;

    public function __construct(
        private readonly CicloRepositoryInterface $ciclos,
    ) {}

    /**
     * Los Grupos rotativos (modalidad=seis_meses) únicamente: el SIAGIE
     * anual ya no vive en este listado, tiene su propio módulo (ver
     * SiagieService::listar()).
     */
    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->ciclos->paginateGrupos($perPage);
    }

    /**
     * @param  array{nombre: string, tipo?: ?TipoCicloEnum, modalidad?: ModalidadCicloEnum, anio: int, fecha_inicio: string, fecha_fin: string}  $datos
     */
    public function crear(array $datos): Ciclo
    {
        $datos['modalidad'] ??= ModalidadCicloEnum::SEIS_MESES;

        $this->validarSegunModalidad($datos['modalidad'], $datos['tipo'] ?? null, $datos['fecha_inicio'], $datos['fecha_fin']);

        return $this->ciclos->create($datos);
    }

    /**
     * @param  array{nombre: string, tipo?: ?TipoCicloEnum, modalidad?: ModalidadCicloEnum, anio: int, fecha_inicio: string, fecha_fin: string, estado: EstadoCicloEnum}  $datos
     */
    public function actualizar(Ciclo $ciclo, array $datos): Ciclo
    {
        $datos['modalidad'] ??= ModalidadCicloEnum::SEIS_MESES;

        $this->validarSegunModalidad($datos['modalidad'], $datos['tipo'] ?? null, $datos['fecha_inicio'], $datos['fecha_fin'], $ciclo->id);

        return $this->ciclos->update($ciclo, $datos);
    }

    private function validarSegunModalidad(ModalidadCicloEnum $modalidad, ?TipoCicloEnum $tipo, string $fechaInicio, string $fechaFin, ?int $exceptoId = null): void
    {
        if ($modalidad === ModalidadCicloEnum::SEIS_MESES) {
            if ($tipo === null) {
                throw ValidationException::withMessages([
                    'tipo' => 'Un ciclo de 6 meses (Grupo rotativo) necesita indicar a qué grupo (1 a 4) pertenece.',
                ]);
            }

            $this->validarFechasDelCiclo($tipo, $fechaInicio, $fechaFin);
            $this->validarSinSolapeDeMismoTipo($tipo, $fechaInicio, $fechaFin, $exceptoId);

            return;
        }

        $this->validarFechasCicloAnual($fechaInicio, $fechaFin);
        $this->validarSinSolapeAnual($fechaInicio, $fechaFin, $exceptoId);
    }

    /**
     * Un ciclo SIAGIE anual no tiene mes de inicio fijo ni Grupo asociado:
     * su periodo de clases dura 8 meses, declarados a mano (de qué mes a
     * qué mes) por quien lo registra -- los 2 meses restantes del año son
     * las vacaciones propias de esta modalidad (ver módulo Vacaciones),
     * fuera del ciclo mismo.
     */
    public function validarFechasCicloAnual(string $fechaInicio, string $fechaFin): void
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        if ($fin->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            ]);
        }

        $finEsperado = $inicio->copy()->addMonths(8);
        $diferenciaEnDias = abs($fin->diffInDays($finEsperado));

        if ($diferenciaEnDias > 15) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'Un ciclo SIAGIE anual dura 8 meses de clases; la fecha de fin no cuadra con la de inicio (margen de 15 días).',
            ]);
        }
    }

    private function validarSinSolapeAnual(string $fechaInicio, string $fechaFin, ?int $exceptoId = null): void
    {
        $solapados = $this->ciclos->solapadosCon($fechaInicio, $fechaFin, $exceptoId)
            ->where('modalidad', ModalidadCicloEnum::ANUAL);

        if ($solapados->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Ya existe un ciclo SIAGIE anual con fechas que se cruzan: '.$solapados->first()->nombre,
            ]);
        }
    }

    /**
     * Primera validación: las fechas del ciclo deben cuadrar con su tipo.
     * Los 4 grupos tienen mes de inicio fijo, y una duración objetivo (6
     * meses) con un margen de una semana para acomodar feriados/ajustes
     * administrativos.
     */
    public function validarFechasDelCiclo(TipoCicloEnum $tipo, string $fechaInicio, string $fechaFin): void
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        if ($fin->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            ]);
        }

        if ($inicio->month !== $tipo->mesInicioFijo()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => "Un ciclo {$tipo->label()} debe iniciar en el mes ".Carbon::create(month: $tipo->mesInicioFijo())->translatedFormat('F').'.',
            ]);
        }

        $mesesEsperados = $tipo->duracionEnMeses();
        $finEsperado = $inicio->copy()->addMonths($mesesEsperados);
        $diferenciaEnDias = abs($fin->diffInDays($finEsperado));

        if ($diferenciaEnDias > 7) {
            throw ValidationException::withMessages([
                'fecha_fin' => "Un ciclo {$tipo->label()} dura {$mesesEsperados} meses; la fecha de fin no cuadra con la de inicio (margen de una semana).",
            ]);
        }
    }

    /**
     * Evita planificar dos ciclos del mismo tipo con fechas que se cruzan
     * (p. ej. dos "Enero-Junio" solapados sería un error de carga).
     */
    private function validarSinSolapeDeMismoTipo(TipoCicloEnum $tipo, string $fechaInicio, string $fechaFin, ?int $exceptoId = null): void
    {
        $solapados = $this->ciclos->solapadosCon($fechaInicio, $fechaFin, $exceptoId)
            ->where('tipo', $tipo);

        if ($solapados->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Ya existe un ciclo del mismo tipo con fechas que se cruzan: '.$solapados->first()->nombre,
            ]);
        }
    }

    /**
     * Segunda validación (la "doble validación" del roadmap): el periodo de
     * matrícula debe caer dentro de una ventana razonable respecto al
     * ciclo — puede abrir hasta 30 días antes de que el ciclo inicie, pero
     * debe cerrar a más tardar cuando el ciclo termina.
     */
    public function crearPeriodoMatricula(Ciclo $ciclo, string $fechaInicio, string $fechaFin): PeriodoMatricula
    {
        $this->validarPeriodoMatriculaContraCiclo($ciclo, $fechaInicio, $fechaFin);

        return $ciclo->periodosMatricula()->create([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);
    }

    public function validarPeriodoMatriculaContraCiclo(Ciclo $ciclo, string $fechaInicio, string $fechaFin): void
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        if ($fin->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin de matrícula debe ser posterior o igual a la de inicio.',
            ]);
        }

        $ventanaInicio = $ciclo->fecha_inicio->copy()->subDays(self::DIAS_MATRICULA_ANTICIPADA);

        if ($inicio->lessThan($ventanaInicio)) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La matrícula no puede abrir con más de '.self::DIAS_MATRICULA_ANTICIPADA." días de anticipación al inicio del ciclo ({$ciclo->fecha_inicio->format('d/m/Y')}).",
            ]);
        }

        if ($fin->greaterThan($ciclo->fecha_fin)) {
            throw ValidationException::withMessages([
                'fecha_fin' => "La matrícula debe cerrar a más tardar el {$ciclo->fecha_fin->format('d/m/Y')}, fecha en que termina el ciclo.",
            ]);
        }
    }

    /**
     * El grupo al que pasaría un estudiante de $actual al culminar su
     * grado (ver TipoCicloEnum::siguiente()), si ya existe una fila
     * creada para ese grupo+año. Null si todavía no se ha creado (p. ej.
     * personal aún no armó el siguiente grupo).
     */
    public function siguienteCiclo(Ciclo $actual): ?Ciclo
    {
        if ($actual->modalidad !== ModalidadCicloEnum::SEIS_MESES || $actual->tipo === null) {
            return null;
        }

        $siguienteAnio = $actual->anio + ($actual->tipo->avanzaAlSiguienteAnio() ? 1 : 0);

        return Ciclo::query()
            ->where('tipo', $actual->tipo->siguiente())
            ->where('anio', $siguienteAnio)
            ->first();
    }

    /**
     * SIAGIE anual no rota entre Grupos como el de 6 meses: a lo sumo hay un
     * ciclo anual "vigente" a la vez, el marcado Activo. Si todavía no hay
     * ninguno activo, cae al más reciente por fecha de inicio. Sin periodo
     * de matrícula que abrir de por medio -- esta modalidad se identifica
     * solo por año, y su matrícula está disponible mientras el ciclo esté
     * vigente (ver MatriculaService::matricular()).
     */
    public function cicloAnualVigente(): ?Ciclo
    {
        return Ciclo::query()
            ->where('modalidad', ModalidadCicloEnum::ANUAL)
            ->where('estado', EstadoCicloEnum::ACTIVO)
            ->orderByDesc('fecha_inicio')
            ->first()
            ?? Ciclo::query()
                ->where('modalidad', ModalidadCicloEnum::ANUAL)
                ->orderByDesc('fecha_inicio')
                ->first();
    }
}
