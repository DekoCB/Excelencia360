<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class GradoService
{
    /**
     * @return Collection<int, Grado>
     */
    public function todos(): Collection
    {
        return Grado::query()->orderBy('orden')->get();
    }

    /**
     * @param  array{programa_estudio_id: int, nombre: string, orden: int}  $datos
     */
    public function crear(array $datos): Grado
    {
        return Grado::query()->create($datos);
    }

    /**
     * @param  array{programa_estudio_id: int, nombre: string, orden: int, activo: bool}  $datos
     */
    public function actualizar(Grado $grado, array $datos): Grado
    {
        $grado->update($datos);

        return $grado;
    }

    public function existeOrden(int $programaEstudioId, int $orden, ?int $exceptoId = null): bool
    {
        return Grado::query()
            ->where('programa_estudio_id', $programaEstudioId)
            ->where('orden', $orden)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }

    /**
     * Historial de Periodos Académicos (Ciclos) en los que este Semestre
     * tuvo matrículas -- un Grado es reutilizable entre ciclos (no tiene
     * relación directa con Ciclo), así que esto se deriva de Matricula,
     * que sí lleva ambas columnas. Puramente informativo.
     *
     * @return SupportCollection<int, array{ciclo: Ciclo, matriculados: int<0, max>}>
     */
    public function periodosHistoricos(Grado $grado): SupportCollection
    {
        $ciclosIds = Matricula::query()
            ->where('grado_id', $grado->id)
            ->distinct()
            ->pluck('ciclo_id');

        return Ciclo::query()
            ->whereIn('id', $ciclosIds)
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (Ciclo $ciclo) => [
                'ciclo' => $ciclo,
                'matriculados' => Matricula::query()
                    ->where('grado_id', $grado->id)
                    ->where('ciclo_id', $ciclo->id)
                    ->count(),
            ]);
    }
}
