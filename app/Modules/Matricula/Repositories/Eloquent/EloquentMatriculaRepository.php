<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Repositories\Eloquent;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Repositories\Contracts\MatriculaRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Matricula>
 */
class EloquentMatriculaRepository extends BaseRepository implements MatriculaRepositoryInterface
{
    /**
     * @return Builder<Matricula>
     */
    protected function query(): Builder
    {
        return Matricula::query()->with(['estudiante', 'ciclo', 'grado']);
    }

    public function existeParaEstudianteYCiclo(int $estudianteId, int $cicloId): bool
    {
        return Matricula::query()
            ->where('estudiante_id', $estudianteId)
            ->where('ciclo_id', $cicloId)
            ->exists();
    }
}
