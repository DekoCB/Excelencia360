<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Contracts;

use App\Modules\Academico\Models\Curso;
use App\Shared\Repositories\RepositoryInterface;

/**
 * @extends RepositoryInterface<Curso>
 */
interface CursoRepositoryInterface extends RepositoryInterface
{
    public function existeCodigo(string $codigo, ?int $exceptoId = null): bool;
}
