<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Repositories\Eloquent;

use App\Modules\Academico\Support\FiltroMatriculaAcademico;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Repositories\Contracts\EstudianteRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Estudiante>
 */
class EloquentEstudianteRepository extends BaseRepository implements EstudianteRepositoryInterface
{
    /**
     * @return Builder<Estudiante>
     */
    protected function query(): Builder
    {
        return Estudiante::query()->with(['gradoActual', 'media', 'user.media']);
    }

    /**
     * Los filtros de ciclo/grado/curso/docente (búsqueda avanzada, §25 del
     * prompt maestro) se resuelven contra las matrículas del estudiante,
     * reutilizando el mismo filtro en cascada que ya usa Reportes -- ver
     * App\Modules\Academico\Support\FiltroMatriculaAcademico.
     */
    public function buscar(
        ?string $termino,
        ?string $estado,
        int $perPage = 15,
        ?int $cicloId = null,
        ?int $gradoId = null,
        ?int $cursoId = null,
        ?int $docenteId = null,
    ): LengthAwarePaginator {
        $sinFiltrosAcademicos = FiltroMatriculaAcademico::sinFiltros($cicloId, $gradoId, $cursoId, null, null, $docenteId);

        return $this->query()
            ->when($termino, fn ($query) => $query->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            }))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->when(! $sinFiltrosAcademicos, fn ($query) => $query->whereHas(
                'matriculas',
                fn ($sub) => FiltroMatriculaAcademico::filtrarMatriculas($sub, $cicloId, $gradoId, $cursoId, null, null, $docenteId),
            ))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function existeDni(string $dni, ?int $exceptoId = null): bool
    {
        return Estudiante::query()
            ->where('dni', $dni)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }
}
