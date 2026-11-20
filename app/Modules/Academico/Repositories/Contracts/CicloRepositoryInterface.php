<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Contracts;

use App\Modules\Academico\Models\Ciclo;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Ciclo>
 */
interface CicloRepositoryInterface extends RepositoryInterface
{
    public function activo(): ?Ciclo;

    /**
     * Todos los ciclos (Grupos de 6 meses y Periodo Académico anual
     * juntos), más recientes primero.
     */
    public function paginateTodos(int $perPage = 15): LengthAwarePaginator;
}
