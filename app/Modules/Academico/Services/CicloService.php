<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Los periodos académicos/semestres (ej. "2026-0", "2026-1", "2026-2")
 * tienen nombre y duración completamente libres: esa numeración es solo
 * una convención del instituto, no una regla que el sistema valide, y
 * varios periodos pueden solaparse en fechas sin problema (ej. un
 * "2026-0" acelerado corriendo en paralelo a "2026-1").
 */
class CicloService
{
    public function __construct(
        private readonly CicloRepositoryInterface $ciclos,
    ) {}

    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->ciclos->paginateTodos($perPage);
    }

    /**
     * @param  array{nombre: string, anio: int, fecha_inicio: string, fecha_fin: string, estado?: EstadoCicloEnum}  $datos
     */
    public function crear(array $datos): Ciclo
    {
        $this->validarFechas($datos['fecha_inicio'], $datos['fecha_fin']);

        return $this->ciclos->create($datos);
    }

    /**
     * @param  array{nombre: string, anio: int, fecha_inicio: string, fecha_fin: string, estado: EstadoCicloEnum}  $datos
     */
    public function actualizar(Ciclo $ciclo, array $datos): Ciclo
    {
        $this->validarFechas($datos['fecha_inicio'], $datos['fecha_fin']);

        return $this->ciclos->update($ciclo, $datos);
    }

    private function validarFechas(string $fechaInicio, string $fechaFin): void
    {
        if (Carbon::parse($fechaFin)->lessThanOrEqualTo(Carbon::parse($fechaInicio))) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            ]);
        }
    }
}
