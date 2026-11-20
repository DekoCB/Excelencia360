<?php

declare(strict_types=1);

namespace App\Modules\Migraciones\Services;

use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Pasar de semestre" no es más que una rematrícula (ver
 * MatriculaService::matricular()) en un periodo académico/semestre
 * destino elegidos a mano -- lo mismo que ya hacía el wizard para una
 * rematrícula individual, pero como herramienta dedicada y con soporte
 * para aplicarlo a varios estudiantes a la vez (ver migrarMasivo(), mismo
 * patrón tolerante a errores por fila que
 * MatriculaService::matricularDesdeFilas()).
 */
class MigracionService
{
    public function __construct(
        private readonly MatriculaService $matriculas,
    ) {}

    /**
     * Cohorte de origen: matrículas vigentes (aprobadas) que coinciden con
     * los filtros elegidos. Todos los filtros son opcionales.
     *
     * @return Collection<int, Matricula>
     */
    public function matriculasVigentes(?int $cicloId, ?int $programaEstudioId, ?int $gradoId): Collection
    {
        return Matricula::query()
            ->where('estado', EstadoMatriculaEnum::APROBADA)
            ->when($cicloId !== null, fn ($q) => $q->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($q) => $q->where('grado_id', $gradoId))
            ->when($programaEstudioId !== null, fn ($q) => $q->whereHas('grado', fn ($qq) => $qq->where('programa_estudio_id', $programaEstudioId)))
            ->with(['estudiante', 'grado', 'ciclo'])
            ->get();
    }

    public function migrar(Matricula $origen, int $cicloDestinoId, int $gradoDestinoId, ?int $registradoPor): Matricula
    {
        return $this->matriculas->matricular($origen->estudiante, new RegistrarMatriculaData(
            cicloId: $cicloDestinoId,
            gradoId: $gradoDestinoId,
            observaciones: null,
            registradoPor: $registradoPor,
        ));
    }

    /**
     * @param  Collection<int, Matricula>  $origenes
     * @return array{exitosos: int, errores: list<array{estudiante: string, mensaje: string}>}
     */
    public function migrarMasivo(Collection $origenes, int $cicloDestinoId, int $gradoDestinoId, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($origenes as $origen) {
            try {
                $this->migrar($origen, $cicloDestinoId, $gradoDestinoId, $registradoPor);
                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = [
                    'estudiante' => $origen->estudiante->nombreCompleto(),
                    'mensaje' => $e instanceof ValidationException ? $e->validator->errors()->first() : $e->getMessage(),
                ];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    public function gradoSiguiente(Grado $origen): ?Grado
    {
        return Grado::query()
            ->where('programa_estudio_id', $origen->programa_estudio_id)
            ->where('orden', $origen->orden + 1)
            ->first();
    }
}
