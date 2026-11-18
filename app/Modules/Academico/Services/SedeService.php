<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Sede;
use Illuminate\Database\Eloquent\Collection;

class SedeService
{
    /**
     * @return Collection<int, Sede>
     */
    public function todas(): Collection
    {
        return Sede::query()->withCount('aulas')->orderBy('nombre')->get();
    }

    /**
     * @return Collection<int, Sede>
     */
    public function activas(): Collection
    {
        return Sede::query()->where('activa', true)->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string, direccion: ?string}  $datos
     */
    public function crear(array $datos): Sede
    {
        return Sede::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, direccion: ?string, activa: bool}  $datos
     */
    public function actualizar(Sede $sede, array $datos): Sede
    {
        $sede->update($datos);

        return $sede;
    }
}
