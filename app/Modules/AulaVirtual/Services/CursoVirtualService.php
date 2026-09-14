<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Repositories\Contracts\CursoVirtualRepositoryInterface;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Collection;

class CursoVirtualService
{
    public function __construct(
        private readonly CursoVirtualRepositoryInterface $cursos,
    ) {}

    /**
     * @return Collection<int, CursoVirtual>
     */
    public function delDocente(int $docenteId): Collection
    {
        return $this->cursos->delDocente($docenteId);
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
        return $this->cursos->delEstudiante($estudiante);
    }

    /**
     * Todos los cursos virtuales activos, para supervisión administrativa
     * (Dirección/Coordinador).
     *
     * @return Collection<int, CursoVirtual>
     */
    public function todos(): Collection
    {
        return $this->cursos->all();
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
