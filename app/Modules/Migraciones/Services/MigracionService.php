<?php

declare(strict_types=1);

namespace App\Modules\Migraciones\Services;

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CicloService;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Pasar de grado" no es más que una rematrícula (ver
 * MatriculaService::matricular()) en un ciclo/grado destino elegidos a
 * mano -- lo mismo que ya hacía el wizard para una rematrícula individual,
 * pero como herramienta dedicada y con soporte para aplicarlo a varios
 * estudiantes a la vez (ver migrarMasivo(), mismo patrón tolerante a
 * errores por fila que MatriculaService::matricularDesdeFilas()).
 */
class MigracionService
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly CicloService $ciclos,
    ) {}

    /**
     * Cohorte de origen: matrículas vigentes (aprobadas) que coinciden con
     * los filtros elegidos. El primer filtro es siempre la modalidad del
     * ciclo (Grupo rotativo de 6 meses vs. SIAGIE anual) -- ver el
     * comentario de la vista sobre por qué SIAGIE anual no tiene un filtro
     * de "Grupo" propio, a diferencia de 6 meses. $seccion es 'A'/'B' (ver
     * Grado::scopeDeSeccion()); todos los filtros salvo $modalidad son
     * opcionales.
     *
     * @return Collection<int, Matricula>
     */
    public function matriculasVigentes(?ModalidadCicloEnum $modalidad, ?int $cicloId, ?string $seccion, ?int $gradoId): Collection
    {
        return Matricula::query()
            ->where('estado', EstadoMatriculaEnum::APROBADA)
            ->when($modalidad !== null, fn ($q) => $q->whereHas('ciclo', fn ($qq) => $qq->where('modalidad', $modalidad)))
            ->when($cicloId !== null, fn ($q) => $q->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($q) => $q->where('grado_id', $gradoId))
            ->when($seccion !== null, fn ($q) => $q->whereHas('grado', fn ($qq) => $seccion === 'A' ? $qq->where('orden', '<=', 2) : $qq->where('orden', '>', 2)))
            ->with(['estudiante', 'grado', 'ciclo'])
            ->get();
    }

    /**
     * Sirve tanto para acotar la cohorte de origen en modo masivo como
     * para sugerir destino, sin pedirle al usuario que elija un "Grupo"
     * que no existe en la modalidad anual (ver CicloService::cicloAnualVigente()).
     */
    public function cicloAnualVigente(): ?Ciclo
    {
        return $this->ciclos->cicloAnualVigente();
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
        return Grado::query()->where('orden', $origen->orden + 1)->first();
    }

    public function cicloDestinoSugerido(Ciclo $origen, CicloService $ciclos): ?Ciclo
    {
        if ($origen->modalidad === ModalidadCicloEnum::ANUAL) {
            return Ciclo::query()
                ->where('modalidad', ModalidadCicloEnum::ANUAL)
                ->where('anio', $origen->anio + 1)
                ->first();
        }

        return $ciclos->siguienteCiclo($origen);
    }
}
