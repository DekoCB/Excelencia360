<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Eloquent;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Ciclo>
 */
class EloquentCicloRepository extends BaseRepository implements CicloRepositoryInterface
{
    protected function query(): Builder
    {
        return Ciclo::query();
    }

    public function solapadosCon(string $fechaInicio, string $fechaFin, ?int $exceptoId = null): Collection
    {
        return Ciclo::query()
            ->where('fecha_inicio', '<=', $fechaFin)
            ->where('fecha_fin', '>=', $fechaInicio)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->get();
    }

    public function activo(): ?Ciclo
    {
        return Ciclo::query()->where('estado', EstadoCicloEnum::ACTIVO)->first();
    }

    public function paginateGrupos(int $perPage = 15): LengthAwarePaginator
    {
        return Ciclo::query()->where('modalidad', ModalidadCicloEnum::SEIS_MESES)->paginate($perPage);
    }
}
