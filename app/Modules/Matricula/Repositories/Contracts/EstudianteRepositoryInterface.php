<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Repositories\Contracts;

use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Estudiante>
 */
interface EstudianteRepositoryInterface extends RepositoryInterface
{
    public function buscar(?string $termino, ?string $estado, int $perPage = 15): LengthAwarePaginator;

    public function existeDni(string $dni, ?int $exceptoId = null): bool;
}
