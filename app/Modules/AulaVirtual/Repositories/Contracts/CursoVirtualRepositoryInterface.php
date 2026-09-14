<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Repositories\Contracts;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<CursoVirtual>
 */
interface CursoVirtualRepositoryInterface extends RepositoryInterface
{
    /**
     * Cursos virtuales de los horarios que dicta este docente.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delDocente(int $docenteId): Collection;

    /**
     * Cursos virtuales visibles para un estudiante: los que corresponden al
     * grado y ciclo de alguna de sus matrículas aprobadas.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delEstudiante(Estudiante $estudiante): Collection;

    public function paraHorario(int $horarioId): ?CursoVirtual;
}
