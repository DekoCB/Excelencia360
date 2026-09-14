<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Repositories\Eloquent;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Repositories\Contracts\CursoVirtualRepositoryInterface;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CursoVirtual>
 */
class EloquentCursoVirtualRepository extends BaseRepository implements CursoVirtualRepositoryInterface
{
    /**
     * @return Builder<CursoVirtual>
     */
    protected function query(): Builder
    {
        return CursoVirtual::query()->with(['horario.curso', 'horario.grado', 'horario.ciclo', 'horario.docente', 'horario.dias']);
    }

    public function delDocente(int $docenteId): Collection
    {
        return $this->query()
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docenteId))
            ->get();
    }

    public function delEstudiante(Estudiante $estudiante): Collection
    {
        $matriculas = $estudiante->matriculas()
            ->where('estado', 'aprobada')
            ->get(['id', 'grado_id', 'ciclo_id']);

        if ($matriculas->isEmpty()) {
            return new Collection;
        }

        /** @param Builder<Horario> $query */
        $filtroPorMatriculas = function (Builder $query) use ($matriculas) {
            foreach ($matriculas as $matricula) {
                $condicion = function (Builder $query) use ($matricula) {
                    /** @var Builder<Horario> $query */
                    $query->deLaMatricula($matricula);
                };

                $query->orWhere($condicion);
            }
        };

        return $this->query()
            ->where('activo', true)
            ->whereHas('horario', fn (Builder $query) => $query->where($filtroPorMatriculas))
            ->get();
    }

    public function paraHorario(int $horarioId): ?CursoVirtual
    {
        return $this->query()->where('horario_id', $horarioId)->first();
    }
}
