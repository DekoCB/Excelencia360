<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Contracts;

use App\Modules\Academico\Models\Ciclo;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Ciclo>
 */
interface CicloRepositoryInterface extends RepositoryInterface
{
    /**
     * Ciclos que se solapan en fecha con el rango dado (mismo tipo o no),
     * usado para evitar planificar dos ciclos del mismo tipo al mismo tiempo.
     *
     * @return Collection<int, Ciclo>
     */
    public function solapadosCon(string $fechaInicio, string $fechaFin, ?int $exceptoId = null): Collection;

    public function activo(): ?Ciclo;

    /**
     * Los Grupos rotativos (modalidad=seis_meses) únicamente -- el listado
     * de Grupos ya no incluye el SIAGIE anual (ver SiagieService).
     */
    public function paginateGrupos(int $perPage = 15): LengthAwarePaginator;
}
