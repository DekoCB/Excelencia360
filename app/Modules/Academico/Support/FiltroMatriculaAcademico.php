<?php

declare(strict_types=1);

namespace App\Modules\Academico\Support;

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Horario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Filtro en cascada Grupo (ciclo) → Grado → Curso/Docente, con franja
 * institucional opcional, aplicado a una consulta de Matricula. Extraído
 * de App\Modules\Reportes\Services\ReporteService (que lo usaba de forma
 * privada en 5 de sus 7 reportes) para que App\Modules\Matricula lo
 * reutilice en la búsqueda avanzada de estudiantes (§25 del prompt
 * maestro) sin duplicar la lógica de "paralelos" -- funciones puras,
 * sin estado propio, movidas tal cual, no reescritas.
 */
final class FiltroMatriculaAcademico
{
    public static function sinFiltros(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja, ?int $siagieId = null, ?int $docenteId = null): bool
    {
        return $cicloId === null && $gradoId === null && $cursoId === null && $franja === null && $siagieId === null && $docenteId === null;
    }

    /**
     * Horarios que coinciden con el Grupo (ciclo), Grado, Curso y/o
     * Docente elegidos, más la franja institucional si se usa. null en
     * cualquiera de los filtros significa "no restringir por ese campo".
     * Devuelve null si no se pidió ningún filtro (para que el caller no
     * aplique ninguna restricción en absoluto).
     *
     * @return ?Collection<int, Horario>
     */
    public static function horariosFiltrados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja, ?int $docenteId = null): ?Collection
    {
        if (self::sinFiltros($cicloId, $gradoId, $cursoId, $franja, null, $docenteId)) {
            return null;
        }

        $franjaEnum = $franja !== null ? FranjaHorarioEnum::tryFrom($franja) : null;

        return Horario::query()
            ->with('dias')
            ->when($cicloId !== null, fn ($query) => $query->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($query) => $query->where('grado_id', $gradoId))
            ->when($cursoId !== null, fn ($query) => $query->where('curso_id', $cursoId))
            ->when($docenteId !== null, fn ($query) => $query->where('docente_id', $docenteId))
            ->get()
            ->filter(fn (Horario $horario) => $franjaEnum === null || $horario->franja() === $franjaEnum)
            ->values();
    }

    /**
     * @return ?list<int>
     */
    public static function horarioIdsFiltrados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja, ?int $docenteId = null): ?array
    {
        return self::horariosFiltrados($cicloId, $gradoId, $cursoId, $franja, $docenteId)?->pluck('id')->all();
    }

    /**
     * Aplica el filtro de SIAGIE/Grupo/Grado/Curso/Docente/franja a una
     * consulta de Matricula. SIAGIE, ciclo y grado se filtran directo por
     * columna (Matricula ya las tiene). Curso, docente y franja no
     * existen ahí -- se resuelven vía Horario: un estudiante matriculado
     * en ese grado+ciclo lleva automáticamente cualquier curso de su
     * grado, salvo que ese curso tenga secciones paralelas, donde se
     * exige la asignación explícita en el pivote matricula_horario a uno
     * de los horarios filtrados (mismo criterio que
     * Matricula::scopeDelHorario()).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function filtrarMatriculas(Builder $query, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja, ?int $siagieId = null, ?int $docenteId = null): Builder
    {
        $query = $query
            ->when($cicloId !== null, fn ($q) => $q->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($q) => $q->where('grado_id', $gradoId))
            ->when($siagieId !== null, fn ($q) => $q->where('siagie_id', $siagieId));

        if ($cursoId === null && $franja === null && $docenteId === null) {
            return $query;
        }

        // docente_id (como franja) solo acota QUÉ horarios cuentan para
        // derivar los pares grado+ciclo de abajo -- un estudiante de ese
        // grado+ciclo lleva automáticamente el curso de ese docente salvo
        // que el propio curso tenga paralelos, donde SÍ se exige la
        // asignación explícita (el mismo criterio de siempre, gatillado
        // únicamente por $cursoId, no por $docenteId).
        $horarios = self::horariosFiltrados($cicloId, $gradoId, $cursoId, $franja, $docenteId);

        if ($horarios === null || $horarios->isEmpty()) {
            return $query->whereIn('id', []);
        }

        // Si ciclo o grado no venían fijos arriba, hace falta acotar la
        // matrícula a los pares grado+ciclo donde sí cae la franja/curso/docente.
        if ($cicloId === null || $gradoId === null) {
            $pares = $horarios
                ->map(fn (Horario $horario) => ['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id])
                ->unique(fn (array $par) => $par['grado_id'].'-'.$par['ciclo_id'])
                ->values();

            $query = self::filtrarPorGradoYCiclo($query, $pares);
        }

        if ($cursoId === null) {
            return $query;
        }

        $tieneParalelos = Horario::query()->where('curso_id', $cursoId)->count() > 1;

        if (! $tieneParalelos) {
            return $query;
        }

        return $query->whereHas('horarios', fn ($sub) => $sub->whereIn('horarios.id', $horarios->pluck('id')));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, array{grado_id: int, ciclo_id: int}>  $pares
     * @return Builder<TModel>
     */
    public static function filtrarPorGradoYCiclo(Builder $query, Collection $pares): Builder
    {
        if ($pares->isEmpty()) {
            return $query->whereIn('id', []);
        }

        return $query->where(function (Builder $q) use ($pares) {
            foreach ($pares as $par) {
                $q->orWhere(fn (Builder $qq) => $qq->where('grado_id', $par['grado_id'])->where('ciclo_id', $par['ciclo_id']));
            }
        });
    }
}
