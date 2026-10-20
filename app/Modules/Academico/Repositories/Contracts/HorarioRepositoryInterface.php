<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Contracts;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Horario>
 */
interface HorarioRepositoryInterface extends RepositoryInterface
{
    /**
     * Días (de otros horarios) que ya ocupan la misma aula, mismo día y
     * mismo ciclo, con un rango horario que se cruza con
     * [$horaInicio, $horaFin). $cicloId null (horario de curso corto)
     * solo cruza contra otros horarios de curso corto (también sin
     * ciclo) -- no contra los horarios de Instituto, que viven en su
     * propio período acotado.
     *
     * @return Collection<int, HorarioDia>
     */
    public function enAulaQueSolapan(int $aulaId, ?int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoHorarioId = null): Collection;

    /**
     * Igual que {@see enAulaQueSolapan} pero para el mismo docente: un
     * profesor no puede dictar dos cursos a la misma hora aunque sea en
     * aulas distintas.
     *
     * @return Collection<int, HorarioDia>
     */
    public function delDocenteQueSolapan(int $docenteId, ?int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoHorarioId = null): Collection;

    /**
     * @return Collection<int, Horario>
     */
    public function delCiclo(int $cicloId): Collection;

    /**
     * Horarios de curso corto (ciclo_id null) -- no viven dentro de
     * ningún ciclo, así que no se filtran por él como delCiclo().
     *
     * @return Collection<int, Horario>
     */
    public function deCursoCorto(): Collection;
}
