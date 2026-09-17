<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Repositories\Contracts\CursoVirtualRepositoryInterface;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Collection;

class CursoVirtualService
{
    public function __construct(
        private readonly CursoVirtualRepositoryInterface $cursos,
        private readonly CicloRepositoryInterface $ciclos,
    ) {}

    /**
     * El período de matrícula (Ciclo) "actual" para acotar el catálogo de
     * cursos virtuales: el que está marcado como activo, o si ninguno lo
     * está (entre períodos), el de fecha de inicio más reciente -- mismo
     * criterio de respaldo que CicloService::cicloAnualVigente().
     */
    private function cicloActualId(): ?int
    {
        $ciclo = $this->ciclos->activo()
            ?? Ciclo::query()->orderByDesc('fecha_inicio')->first();

        return $ciclo?->id;
    }

    /**
     * @return Collection<int, CursoVirtual>
     */
    public function delDocente(int $docenteId): Collection
    {
        return $this->cursos->delDocente($docenteId, $this->cicloActualId());
    }

    /**
     * Los cursos virtuales que corresponden al mismo curso académico, grado
     * y ciclo que $curso -- incluido $curso mismo -- aunque los dicte otro
     * docente. Es la lista para "subir también a": replicar un material a
     * los demás cursos virtuales equivalentes sin tener que repetirlo uno
     * por uno.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function cursosVirtualesRelacionados(CursoVirtual $curso): Collection
    {
        $horario = $curso->horario;

        return CursoVirtual::query()
            ->whereHas('horario', function ($query) use ($horario) {
                $query->where('curso_id', $horario->curso_id)
                    ->where('grado_id', $horario->grado_id)
                    ->where('ciclo_id', $horario->ciclo_id);
            })
            ->with(['horario.curso', 'horario.grado', 'horario.ciclo', 'horario.docente'])
            ->get()
            ->sortBy(fn (CursoVirtual $cursoVirtual) => $cursoVirtual->horario->docente->name ?? '');
    }

    /**
     * @return Collection<int, CursoVirtual>
     */
    public function delEstudiante(Estudiante $estudiante): Collection
    {
        return $this->cursos->delEstudiante($estudiante, $this->cicloActualId());
    }

    /**
     * Todos los cursos virtuales activos del período de matrícula actual,
     * para supervisión administrativa (Dirección/Coordinador).
     *
     * @return Collection<int, CursoVirtual>
     */
    public function todos(): Collection
    {
        return $this->cursos->todos($this->cicloActualId());
    }

    /**
     * Activa el aula virtual para un horario. Si ya existe, simplemente la
     * retorna (idempotente): un docente puede volver a "activar" sin miedo
     * a duplicar el curso.
     */
    public function activarParaHorario(Horario $horario): CursoVirtual
    {
        $existente = $this->cursos->paraHorario($horario->id);

        if ($existente) {
            return $existente;
        }

        return $this->cursos->create([
            'horario_id' => $horario->id,
            'activo' => true,
        ]);
    }
}
