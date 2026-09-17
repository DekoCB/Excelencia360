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
     * Todos los cursos virtuales activos, opcionalmente acotados a un
     * período de matrícula (Horario::ciclo_id).
     *
     * @return Collection<int, CursoVirtual>
     */
    public function todos(?int $cicloId = null): Collection;

    /**
     * Cursos virtuales de los horarios que dicta este docente, opcionalmente
     * acotados a un período de matrícula.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delDocente(int $docenteId, ?int $cicloId = null): Collection;

    /**
     * Cursos virtuales visibles para un estudiante: los que corresponden al
     * grado y ciclo de alguna de sus matrículas aprobadas, opcionalmente
     * acotados además a un período de matrícula concreto.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delEstudiante(Estudiante $estudiante, ?int $cicloId = null): Collection;

    public function paraHorario(int $horarioId): ?CursoVirtual;
}
