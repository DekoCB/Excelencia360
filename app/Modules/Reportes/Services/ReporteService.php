<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Services;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Support\FiltroMatriculaAcademico;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Enums\RolEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Support\Carbon;

/**
 * Constructor de reportes tabulares exportables. Cada método devuelve
 * ['columnas' => list<string>, 'filas' => list<list<string|int|float>>]
 * en el mismo formato para alimentar tanto la vista previa en pantalla
 * como los exportadores de Excel/CSV/PDF sin transformación adicional.
 *
 * El filtro común a todos es Ciclo (Periodo Académico) → Grado → Curso,
 * en cascada, más franja institucional -- ya no hay filtro por rango de
 * fechas: el ciclo (que ya tiene su propio periodo) alcanza para acotar
 * el reporte a un periodo lectivo concreto.
 */
class ReporteService
{
    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function matricula(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $matriculas = FiltroMatriculaAcademico::filtrarMatriculas(
            Matricula::query()->with(['estudiante', 'grado', 'ciclo']),
            $cicloId,
            $gradoId,
            $cursoId,
            $franja,
        )->latest('fecha_matricula')->get();

        return [
            'columnas' => ['Estudiante', 'DNI', 'Grado', 'Ciclo', 'Estado', 'Fecha de matrícula'],
            'filas' => $matriculas->map(fn (Matricula $matricula) => [
                $matricula->estudiante?->nombreCompleto() ?? '—',
                $matricula->estudiante->dni ?? '—',
                $matricula->grado->nombre,
                $matricula->ciclo->nombre,
                $matricula->estado->label(),
                $matricula->fecha_matricula->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * Una fila por componente de evaluación (Criterios de cada Unidad,
     * Promedio de Unidad, Examen Final, Asistencia, Sustitutorio,
     * Aplazado, Promedio Final) de cada curso de cada estudiante --
     * mismo formato que el "Estado académico detalle" que ya maneja la
     * institución en otro sistema, reutilizando el motor de notas ya
     * construido (ver EvaluacionService::desgloseDelEstudiante()/
     * promedioFinalDelEstudiante()) en vez de recalcular la fórmula acá.
     * Sin columna de docente a propósito.
     *
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function academico(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = FiltroMatriculaAcademico::horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $horarios = Horario::query()
            ->with(['grado', 'curso'])
            ->whereNotNull('ciclo_id')
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('id', $horarioIds))
            ->get();

        $filas = [];

        foreach ($horarios as $horario) {
            foreach ($this->evaluaciones->estudiantesDelHorario($horario) as $estudiante) {
                $filasEstudiante = $this->filasAcademicoDelEstudiante($estudiante, $horario);

                if ($filasEstudiante !== []) {
                    array_push($filas, ...$filasEstudiante);
                }
            }
        }

        return [
            'columnas' => ['Estudiante', 'Grado', 'Curso', 'Componente', 'Nota'],
            'filas' => $filas,
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function filasAcademicoDelEstudiante(Estudiante $estudiante, Horario $horario): array
    {
        $calificaciones = $this->evaluaciones->misCalificaciones($estudiante, $horario);

        if ($calificaciones->isEmpty()) {
            return [];
        }

        $desglose = $this->evaluaciones->desgloseDelEstudiante($estudiante, $horario);
        $promedioFinal = $this->evaluaciones->promedioFinalDelEstudiante($estudiante, $horario);

        $filas = [];
        $agregar = function (string $componente, ?float $nota) use (&$filas, $estudiante, $horario) {
            $filas[] = [
                $estudiante->nombreCompleto(),
                $horario->grado->nombre,
                $horario->curso->nombre,
                $componente,
                $nota !== null ? number_format($nota, 2) : '—',
            ];
        };

        foreach ([1 => 'I', 2 => 'II'] as $unidad => $romano) {
            $criterios = $calificaciones
                ->filter(fn ($calificacion) => $calificacion->evaluacion->rol === RolEvaluacionEnum::CRITERIO && $calificacion->evaluacion->unidad === $unidad)
                ->sortBy(fn ($calificacion) => $calificacion->evaluacion->id)
                ->values();

            foreach ($criterios as $indice => $calificacion) {
                $agregar('Criterio '.($indice + 1)." U{$unidad}", (float) $calificacion->nota_numerica);
            }

            $agregar("Prom {$romano} Unidad", $desglose["unidad{$unidad}"]);
        }

        $agregar('Examen Final', $desglose['examenFinal']);
        $agregar('Asistencia', $desglose['asistencia']);
        $agregar('Examen Sustitutorio', $desglose['sustitutorio']);
        $agregar('Examen Aplazado', $desglose['aplazado']);
        $agregar('Promedio Final', $promedioFinal);

        return $filas;
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function financiero(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = FiltroMatriculaAcademico::sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $pagos = Pago::query()
            ->with(['estudiante', 'concepto', 'cargoAdicional'])
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'estudiante.matriculas',
                fn ($sub) => FiltroMatriculaAcademico::filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, $franja),
            ))
            ->latest('fecha_pago')
            ->get();

        return [
            'columnas' => ['Estudiante', 'Concepto', 'Monto', 'Método', 'Estado', 'Fecha de pago'],
            'filas' => $pagos->map(fn (Pago $pago) => [
                $pago->estudiante?->nombreCompleto() ?? '—',
                $pago->nombreConcepto(),
                number_format((float) $pago->monto, 2),
                $pago->metodo->label(),
                $pago->estado->label(),
                $pago->fecha_pago->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function certificados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = FiltroMatriculaAcademico::sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $certificados = Certificado::query()
            ->with(['estudiante', 'matricula.grado'])
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'matricula',
                fn ($sub) => FiltroMatriculaAcademico::filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, $franja),
            ))
            ->latest('fecha_emision')
            ->get();

        return [
            'columnas' => ['N.° certificado', 'Estudiante', 'Grado', 'Duplicado', 'Fecha de emisión'],
            'filas' => $certificados->map(fn (Certificado $certificado) => [
                $certificado->numero,
                $certificado->estudiante?->nombreCompleto() ?? '—',
                $certificado->matricula !== null ? $certificado->matricula->grado->nombre : '—',
                $certificado->es_duplicado ? 'Sí' : 'No',
                $certificado->fecha_emision->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function operativo(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = FiltroMatriculaAcademico::horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $asistencias = Asistencia::query()
            ->with(['estudiante', 'horario.grado'])
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('horario_id', $horarioIds))
            ->get()
            ->groupBy(fn (Asistencia $asistencia) => $asistencia->estudiante_id);

        $filas = $asistencias
            ->filter(fn ($registros) => $registros->first()->estudiante !== null)
            ->map(function ($registros) {
                $estudiante = $registros->first()->estudiante;
                $total = $registros->count();
                $presentes = $registros->whereIn('estado', ['presente', 'justificado'])->count();

                return [
                    $estudiante->nombreCompleto(),
                    $registros->first()->horario->grado->nombre,
                    $total,
                    $presentes,
                    $total > 0 ? number_format($presentes / $total * 100, 1).'%' : '—',
                ];
            })->values()->all();

        return [
            'columnas' => ['Estudiante', 'Grado', 'Sesiones registradas', 'Asistencias', '% Asistencia'],
            'filas' => $filas,
        ];
    }

    /**
     * Igual que operativo() pero una fila por registro real de asistencia
     * (con su fecha), en vez de un resumen por estudiante -- para cuando
     * hace falta ver CUÁNDO faltó o asistió cada quien, no solo el total.
     *
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function operativoDetalle(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = FiltroMatriculaAcademico::horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $filas = Asistencia::query()
            ->with(['estudiante', 'horario.grado', 'horario.curso'])
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('horario_id', $horarioIds))
            ->get()
            ->filter(fn (Asistencia $asistencia) => $asistencia->estudiante !== null)
            ->sortByDesc('fecha')
            ->map(fn (Asistencia $asistencia) => [
                $asistencia->estudiante->nombreCompleto(),
                $asistencia->horario->grado->nombre,
                $asistencia->horario->curso->nombre,
                // $asistencia->fecha es un string "Y-m-d" sin cast a Carbon
                // a propósito (ver Asistencia::$fecha) -- se reformatea acá
                // solo para mostrar, sin tocar el modelo.
                Carbon::parse($asistencia->fecha)->format('d/m/Y'),
                $asistencia->estado->label(),
            ])->values()->all();

        return [
            'columnas' => ['Estudiante', 'Grado', 'Curso', 'Fecha', 'Estado'],
            'filas' => $filas,
        ];
    }

    /**
     * Un estudiante por fila, con sus cuotas vencidas sin pagar agrupadas:
     * cuántas, cuánto suman y desde cuándo la más antigua está vencida.
     * Solo cuenta cuotas ya vencidas a hoy, sin importar el filtro elegido
     * -- eso es lo que define a un "deudor", no una fecha de corte manual.
     *
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function morosos(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = FiltroMatriculaAcademico::sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $cuotasVencidas = Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'planPago.matricula',
                fn ($sub) => FiltroMatriculaAcademico::filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, $franja),
            ))
            ->with('planPago.matricula.estudiante', 'planPago.matricula.grado')
            ->get()
            ->filter(fn (Cuota $cuota) => $cuota->planPago->matricula?->estudiante !== null)
            ->groupBy(fn (Cuota $cuota) => $cuota->planPago->matricula->estudiante_id);

        $filas = $cuotasVencidas
            ->map(function ($cuotas) {
                $matricula = $cuotas->first()->planPago->matricula;
                $estudiante = $matricula->estudiante;

                return [
                    $estudiante->nombreCompleto(),
                    $estudiante->dni,
                    $matricula->grado->nombre,
                    $cuotas->count(),
                    number_format((float) $cuotas->sum('monto'), 2),
                    $cuotas->min('fecha_vencimiento')->format('d/m/Y'),
                ];
            })
            ->sortByDesc(fn (array $fila) => $fila[3])
            ->values()
            ->all();

        return [
            'columnas' => ['Estudiante', 'DNI', 'Grado', 'Cuotas vencidas', 'Monto adeudado', 'Vencida desde'],
            'filas' => $filas,
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function propio(User $docente, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = FiltroMatriculaAcademico::horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $evaluaciones = Evaluacion::query()
            ->with(['horario.grado', 'horario.curso'])
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docente->id))
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('horario_id', $horarioIds))
            ->latest('fecha')
            ->get();

        return [
            'columnas' => ['Evaluación', 'Grado', 'Curso', 'Fecha', 'Estado'],
            'filas' => $evaluaciones->map(fn (Evaluacion $evaluacion) => [
                $evaluacion->nombre,
                $evaluacion->horario->grado->nombre,
                $evaluacion->horario->curso->nombre,
                $evaluacion->fecha->format('d/m/Y'),
                $evaluacion->estado->label(),
            ])->all(),
        ];
    }
}
