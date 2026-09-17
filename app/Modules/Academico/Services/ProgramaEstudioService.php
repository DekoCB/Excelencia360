<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\ProgramaEstudio;
use Illuminate\Database\Eloquent\Collection;

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
}
