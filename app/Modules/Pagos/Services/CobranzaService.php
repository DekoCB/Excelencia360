<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cobros: qué debe un estudiante puntual (vista individual del módulo de
 * Cobranza), o qué estudiantes deben uno o más conceptos dentro de un
 * Grupo/Grado/Curso filtrado (vista grupal).
 *
 * "Deber" se resuelve distinto según el tipo de concepto. Mensualidad
 * (Cuotas de un PlanPago) y los cargos adicionales puntuales por estudiante
 * (CargoAdicional -- Convalidación, Exoneración, Recuperación, Visación...,
 * agregados al matricular) sí tienen una obligación real registrada de
 * antemano. El resto del catálogo (Matrícula, Certificado, Constancia,
 * Penalidad, Otro) no tiene ninguna obligación registrada de antemano:
 * solo existe un Pago si alguien ya intentó pagar. Ahí "deber" se
 * interpreta como tener un Pago Pendiente (esperando aprobación de
 * Tesorería) o Rechazado (hay que volver a pagar) para ese concepto -- la
 * única señal real que existe en el sistema para esos casos.
 */
class CobranzaService
{
    /**
     * @return array{cuotasPendientes: Collection<int, Cuota>, cargosAdicionalesPendientes: Collection<int, CargoAdicional>, pagosPendientes: Collection<int, Pago>, pagosAprobados: Collection<int, Pago>}
     */
    public function deudaDeEstudiante(Estudiante $estudiante): array
    {
        $cuotasPendientes = Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->with('planPago.matricula.grado')
            ->orderBy('fecha_vencimiento')
            ->get();

        $cargosAdicionalesPendientes = CargoAdicional::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->latest()
            ->get();

        $pagosPendientes = Pago::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereIn('estado', [EstadoPagoEnum::PENDIENTE, EstadoPagoEnum::RECHAZADO])
            ->with(['concepto', 'cargoAdicional'])
            ->latest('fecha_pago')
            ->get();

        // Lo ya cobrado (aprobado): el detalle completo de "ya pagado", a
        // diferencia de las colecciones de arriba que son deuda.
        $pagosAprobados = Pago::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->with(['concepto', 'cargoAdicional', 'recibo', 'partes'])
            ->latest('fecha_pago')
            ->get();

        return [
            'cuotasPendientes' => $cuotasPendientes,
            'cargosAdicionalesPendientes' => $cargosAdicionalesPendientes,
            'pagosPendientes' => $pagosPendientes,
            'pagosAprobados' => $pagosAprobados,
        ];
    }

    /**
     * La cuota de mensualidad que le toca pagar a continuación a un
     * estudiante (la de vencimiento más próximo, sin importar si ya venció
     * o no) -- usada por "Registrar pago" para vincular automáticamente el
     * pago a su Cuota cuando el concepto elegido es Mensualidad, en vez de
     * dejar el pago suelto sin Grupo/Cuota (ver PagoService::registrar()).
     */
    public function cuotaPendienteMasProxima(Estudiante $estudiante): ?Cuota
    {
        return Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->with('planPago.matricula.ciclo')
            ->orderBy('fecha_vencimiento')
            ->first();
    }

    /**
     * @param  list<int>  $conceptoIds
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function deudoresPorConceptos(array $conceptoIds, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): array
    {
        $conceptos = ConceptoPago::query()->whereIn('id', $conceptoIds)->get();

        $filas = [];

        foreach ($conceptos as $concepto) {
            $filasDelConcepto = $concepto->tipo === TipoConceptoEnum::MENSUALIDAD
                ? $this->deudoresMensualidad($concepto, $cicloId, $gradoId, $cursoId, $franja)
                : $this->deudoresConceptoLibre($concepto, $cicloId, $gradoId, $cursoId, $franja);

            array_push($filas, ...$filasDelConcepto);
        }

        return [
            'columnas' => ['Estudiante', 'DNI', 'Grado', 'Concepto', 'Detalle', 'Monto'],
            'filas' => $filas,
        ];
    }

    /**
     * @return list<array<int, string|int|float>>
     */
    private function deudoresMensualidad(ConceptoPago $concepto, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): array
    {
        $cuotas = Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->whereHas('planPago.matricula', fn ($sub) => $this->filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, $franja))
            ->with('planPago.matricula.estudiante', 'planPago.matricula.grado')
            ->get()
            ->filter(fn (Cuota $cuota) => $cuota->planPago->matricula?->estudiante !== null);

        return $cuotas->map(function (Cuota $cuota) use ($concepto) {
            $matricula = $cuota->planPago->matricula;
            $estudiante = $matricula->estudiante;
            $vencida = $cuota->estaVencida();

            return [
                $estudiante->nombreCompleto(),
                $estudiante->dni,
                $matricula->grado->nombre,
                $concepto->nombre,
                'Cuota '.$cuota->numero.' — '.($vencida ? 'vencida desde ' : 'vence el ').$cuota->fecha_vencimiento->format('d/m/Y'),
                number_format($cuota->saldoPendiente(), 2),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<int, string|int|float>>
     */
    private function deudoresConceptoLibre(ConceptoPago $concepto, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): array
    {
        $sinFiltros = $this->sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $pagos = Pago::query()
            ->where('concepto_id', $concepto->id)
            ->whereIn('estado', [EstadoPagoEnum::PENDIENTE, EstadoPagoEnum::RECHAZADO])
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'estudiante.matriculas',
                fn ($sub) => $this->filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, $franja),
            ))
            ->with('estudiante.gradoActual')
            ->get();

        return $pagos->map(function (Pago $pago) use ($concepto) {
            $estudiante = $pago->estudiante;
            $grado = $estudiante?->gradoActual;

            return [
                $estudiante?->nombreCompleto() ?? '—',
                $estudiante !== null ? $estudiante->dni : '—',
                $grado !== null ? $grado->nombre : '—',
                $concepto->nombre,
                $pago->estado === EstadoPagoEnum::RECHAZADO
                    ? 'Rechazado'.($pago->motivo_rechazo ? ' — '.$pago->motivo_rechazo : '')
                    : 'Pendiente de aprobación',
                number_format((float) $pago->monto, 2),
            ];
        })->values()->all();
    }

    private function sinFiltros(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): bool
    {
        return $cicloId === null && $gradoId === null && $cursoId === null && $franja === null;
    }

    /**
     * Horarios que coinciden con el Grupo (ciclo), Grado y Curso elegidos,
     * más la franja institucional si se usa -- mismo criterio que
     * ReporteService, ver ese archivo para el razonamiento completo.
     *
     * @return ?Collection<int, Horario>
     */
    private function horariosFiltrados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): ?Collection
    {
        if ($this->sinFiltros($cicloId, $gradoId, $cursoId, $franja)) {
            return null;
        }

        $franjaEnum = $franja !== null ? FranjaHorarioEnum::tryFrom($franja) : null;

        return Horario::query()
            ->with('dias')
            ->when($cicloId !== null, fn ($query) => $query->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($query) => $query->where('grado_id', $gradoId))
            ->when($cursoId !== null, fn ($query) => $query->where('curso_id', $cursoId))
            ->get()
            ->filter(fn (Horario $horario) => $franjaEnum === null || $horario->franja() === $franjaEnum)
            ->values();
    }

    /**
     * Aplica el filtro de Grupo/Grado/Curso/franja a una consulta de
     * Matricula. Ciclo y grado se filtran directo por columna; curso y
     * franja se resuelven vía Horario, exigiendo la asignación explícita
     * en matricula_horario cuando el curso tiene secciones paralelas
     * (mismo criterio que Matricula::scopeDelHorario() y ReporteService).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function filtrarMatriculas(Builder $query, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): Builder
    {
        $query = $query
            ->when($cicloId !== null, fn ($q) => $q->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($q) => $q->where('grado_id', $gradoId));

        if ($cursoId === null && $franja === null) {
            return $query;
        }

        $horarios = $this->horariosFiltrados($cicloId, $gradoId, $cursoId, $franja);

        if ($horarios === null || $horarios->isEmpty()) {
            return $query->whereIn('id', []);
        }

        if ($cicloId === null || $gradoId === null) {
            $pares = $horarios
                ->map(fn (Horario $horario) => ['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id])
                ->unique(fn (array $par) => $par['grado_id'].'-'.$par['ciclo_id'])
                ->values();

            $query = $this->filtrarPorGradoYCiclo($query, $pares);
        }

        if ($cursoId === null) {
            return $query;
        }

        $tieneParalelos = Horario::query()->where('curso_id', $cursoId)->count() > 1;

        if (! $tieneParalelos) {
            return $query;
        }

        return $query->whereHas('horarios', fn ($sub) => $sub->whereIn('horarios.id', $horarios->pluck('id')));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, array{grado_id: int, ciclo_id: int}>  $pares
     * @return Builder<TModel>
     */
    private function filtrarPorGradoYCiclo(Builder $query, Collection $pares): Builder
    {
        if ($pares->isEmpty()) {
            return $query->whereIn('id', []);
        }

        return $query->where(function (Builder $q) use ($pares) {
            foreach ($pares as $par) {
                $q->orWhere(fn (Builder $qq) => $qq->where('grado_id', $par['grado_id'])->where('ciclo_id', $par['ciclo_id']));
            }
        });
    }
}
