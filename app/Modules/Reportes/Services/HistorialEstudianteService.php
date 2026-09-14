<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Services;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\ExamenUbicacion;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PagoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Junta en un solo lugar lo que hoy exige entrar módulo por módulo para
 * saber qué le falta a un estudiante: grados cursados, situación de pagos,
 * documentos (de las 3 fuentes distintas en las que viven hoy) y notas.
 * Es de solo lectura -- no reemplaza a la ficha editable de Matrícula.
 */
class HistorialEstudianteService
{
    public function __construct(
        private readonly LibretaService $libretas,
        private readonly PagoService $pagos,
    ) {}

    public function porDni(string $dni): ?array
    {
        return $this->porEstudiante(Estudiante::query()->where('dni', $dni)->first());
    }

    public function porId(int $estudianteId): ?array
    {
        return $this->porEstudiante(Estudiante::query()->find($estudianteId));
    }

    /**
     * @return array{
     *     estudiante: Estudiante,
     *     matriculas: Collection<int, Matricula>,
     *     resumenPagos: array{totalPagado: float, totalPendiente: float, totalExonerado: float, cuotasVencidas: Collection<int, Cuota>, cuotasPendientes: Collection<int, Cuota>, cargosAdicionalesPendientes: Collection<int, CargoAdicional>},
     *     pagos: Collection<int, Pago>,
     *     documentosSubidos: Collection<int, DocumentoEstudiante>,
     *     documentosEmitidos: Collection<int, Certificado>,
     *     libretas: Collection<int, Libreta>,
     *     notasPorCiclo: SupportCollection<int, array{ciclo: Ciclo, cursos: SupportCollection}>,
     *     examenesUbicacion: Collection<int, ExamenUbicacion>,
     * }|null
     */
    private function porEstudiante(?Estudiante $estudiante): ?array
    {
        if ($estudiante === null) {
            return null;
        }

        $matriculas = $estudiante->matriculas()
            ->with(['grado', 'ciclo'])
            ->orderBy('fecha_matricula')
            ->get();

        return [
            'estudiante' => $estudiante,
            'matriculas' => $matriculas,
            'resumenPagos' => $this->resumenPagos($estudiante),
            'pagos' => $this->pagos->misPagos($estudiante),
            'documentosSubidos' => $estudiante->documentos()->with('media')->get(),
            'documentosEmitidos' => Certificado::query()->where('estudiante_id', $estudiante->id)->with('media')->latest('fecha_emision')->get(),
            'libretas' => $this->libretas->misLibretas($estudiante),
            'notasPorCiclo' => $this->notasPorCiclo($estudiante, $matriculas),
            'examenesUbicacion' => $estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
        ];
    }

    /**
     * Pagado/pendiente/exonerado sumados a través de TODAS las matrículas
     * del estudiante (no solo la más reciente), más el detalle de cuotas
     * vencidas -- mismo criterio que
     * BloqueoAccesoService::cuotasVencidasDe(), pero sin acotar a "las que
     * bloquean acceso": aquí interesa el cuadro completo, no la regla de
     * bloqueo.
     *
     * @return array{totalPagado: float, totalPendiente: float, totalExonerado: float, cuotasVencidas: Collection<int, Cuota>, cuotasPendientes: Collection<int, Cuota>, cargosAdicionalesPendientes: Collection<int, CargoAdicional>}
     */
    private function resumenPagos(Estudiante $estudiante): array
    {
        $cuotas = Cuota::query()
            ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->with('planPago.matricula.grado', 'planPago.matricula.ciclo')
            ->get();

        // Cargos adicionales puntuales del estudiante (Convalidación,
        // Exoneración, Recuperación, Visación...) -- mismo cálculo que las
        // cuotas (montoPagado()/saldoPendiente() desde los Pagos aprobados
        // reales, nunca desde el monto nominal), para que sumen y resten
        // igual de bien aquí que las cuotas.
        $cargos = CargoAdicional::query()->where('estudiante_id', $estudiante->id)->get();

        return [
            // Suma lo realmente cobrado (montoPagado()), no el monto nominal
            // de las cuotas ya PAGADO -- así una cuota (o cargo) pendiente
            // con un pago parcial ya aprobado también aporta lo que sí se
            // cobró, en vez de quedar en cero hasta que se complete.
            'totalPagado' => (float) $cuotas->sum(fn (Cuota $cuota) => $cuota->montoPagado())
                + (float) $cargos->sum(fn (CargoAdicional $cargo) => $cargo->montoPagado()),
            // Lo que de verdad falta cobrar (saldoPendiente()), no el monto
            // completo -- si ya tiene un pago parcial aprobado, aquí debe
            // verse solo lo que queda debiendo.
            'totalPendiente' => (float) $cuotas->where('estado', EstadoCuotaEnum::PENDIENTE)->sum(fn (Cuota $cuota) => $cuota->saldoPendiente())
                + (float) $cargos->where('estado', EstadoCuotaEnum::PENDIENTE)->sum(fn (CargoAdicional $cargo) => $cargo->saldoPendiente()),
            'totalExonerado' => (float) $cuotas->where('estado', EstadoCuotaEnum::EXONERADO)->sum('monto')
                + (float) $cargos->where('estado', EstadoCuotaEnum::EXONERADO)->sum('monto'),
            'cuotasVencidas' => $cuotas->filter(fn (Cuota $cuota) => $cuota->estaVencida())->sortBy('fecha_vencimiento')->values(),
            // Pedido del cliente: además de las ya vencidas (arriba), ver
            // las que están por vencer -- sin duplicar: estaVencida() ya
            // implica estado PENDIENTE, así que se descartan aquí.
            'cuotasPendientes' => $cuotas->where('estado', EstadoCuotaEnum::PENDIENTE)->reject(fn (Cuota $cuota) => $cuota->estaVencida())->sortBy('fecha_vencimiento')->values(),
            'cargosAdicionalesPendientes' => $cargos->where('estado', EstadoCuotaEnum::PENDIENTE)->values(),
        ];
    }

    /**
     * Notas por curso de cada ciclo donde el estudiante tuvo una matrícula
     * aprobada -- LibretaService::resumenPorCursos() ya hace el cálculo
     * por un solo ciclo, aquí se itera sobre todo el historial.
     *
     * @param  Collection<int, Matricula>  $matriculas
     * @return SupportCollection<int, array{ciclo: Ciclo, cursos: SupportCollection}>
     */
    private function notasPorCiclo(Estudiante $estudiante, $matriculas): SupportCollection
    {
        return $matriculas
            ->filter(fn (Matricula $matricula) => $matricula->estado === EstadoMatriculaEnum::APROBADA)
            ->map(fn (Matricula $matricula) => [
                'ciclo' => $matricula->ciclo,
                'cursos' => $this->libretas->resumenPorCursos($estudiante, $matricula->ciclo),
            ])
            ->filter(fn (array $entrada) => $entrada['cursos']->isNotEmpty())
            ->values();
    }
}
