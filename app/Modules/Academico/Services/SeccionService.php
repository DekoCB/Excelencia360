<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\SeccionAcademica;
use Illuminate\Database\Eloquent\Collection;

class SeccionService
{
    /**
     * @return Collection<int, SeccionAcademica>
     */
    public function todas(): Collection
    {
        return SeccionAcademica::query()->orderBy('letra')->get();
    }

    /**
     * @return Collection<int, SeccionAcademica>
     */
    public function activas(): Collection
    {
        return SeccionAcademica::query()->where('activa', true)->orderBy('letra')->get();
    }

    /**
     * @param  array{letra: string}  $datos
     */
    public function crear(array $datos): SeccionAcademica
    {
        return SeccionAcademica::query()->create($datos);
    }

    /**
     * @param  array{letra: string, activa: bool}  $datos
     */
    public function actualizar(SeccionAcademica $seccion, array $datos): SeccionAcademica
    {
        $seccion->update($datos);

        return $seccion;
    }
}
