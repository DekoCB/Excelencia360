<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Repositories\Contracts;

use App\Modules\Matricula\Models\Matricula;
use App\Shared\Repositories\RepositoryInterface;

/**
 * @extends RepositoryInterface<Matricula>
 */
interface MatriculaRepositoryInterface extends RepositoryInterface
{
    public function existeParaEstudianteYCiclo(int $estudianteId, int $cicloId): bool;
}
