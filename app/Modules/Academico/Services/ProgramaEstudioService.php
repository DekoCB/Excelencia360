<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ProgramaEstudioService
{
    /**
     * @return Collection<int, ProgramaEstudio>
     */
    public function todos(): Collection
    {
        return ProgramaEstudio::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string}  $datos
     */
    public function crear(array $datos): ProgramaEstudio
    {
        return ProgramaEstudio::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, activo: bool}  $datos
     */
    public function actualizar(ProgramaEstudio $programa, array $datos): ProgramaEstudio
    {
        $programa->update($datos);

        return $programa;
    }

    /**
     * Unidades Didácticas (Cursos) de los semestres de este programa.
     *
     * @return Collection<int, Curso>
     */
    public function cursosDe(ProgramaEstudio $programa): Collection
    {
        return Curso::query()
            ->whereHas('grados', fn ($query) => $query->where('programa_estudio_id', $programa->id))
            ->with('grados')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Estudiantes matriculados en algún semestre de este programa (vía su
     * matrícula más reciente aprobada), con su Grado y Sección.
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesDe(ProgramaEstudio $programa): Collection
    {
        return Estudiante::query()
            ->whereHas('matriculas', fn ($query) => $query->whereHas(
                'grado',
                fn ($q) => $q->where('programa_estudio_id', $programa->id),
            ))
            ->with(['matriculas' => fn ($query) => $query
                ->whereHas('grado', fn ($q) => $q->where('programa_estudio_id', $programa->id))
                ->where('estado', 'aprobada')
                ->with(['grado', 'seccionAcademica'])
                ->latest('fecha_matricula'),
            ])
            ->orderBy('apellidos')
            ->get();
    }

    /**
     * Secciones (A-E) con al menos un estudiante matriculado en este
     * programa, con su conteo -- puramente de reporte.
     *
     * @return SupportCollection<int, array{letra: string, estudiantes: int}>
     */
    public function seccionesConEstudiantes(ProgramaEstudio $programa): SupportCollection
    {
        return $this->estudiantesDe($programa)
            ->flatMap(fn (Estudiante $estudiante) => $estudiante->matriculas)
            ->pluck('seccionAcademica')
            ->filter()
            ->groupBy('letra')
            ->map(fn ($grupo, $letra) => ['letra' => (string) $letra, 'estudiantes' => $grupo->count()])
            ->sortBy('letra')
            ->values();
    }
}
