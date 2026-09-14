<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Eloquent;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Repositories\Contracts\CursoRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Curso>
 */
class EloquentCursoRepository extends BaseRepository implements CursoRepositoryInterface
{
    protected function query(): Builder
    {
        return Curso::query()->with('grado');
    }

    public function existeCodigo(string $codigo, ?int $exceptoId = null): bool
    {
        return Curso::query()
            ->where('codigo', $codigo)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }
}
