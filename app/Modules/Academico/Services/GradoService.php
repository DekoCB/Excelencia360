<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Collection;

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
     * @param  array{nombre: string, orden: int}  $datos
     */
    public function crear(array $datos): Grado
    {
        return Grado::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, orden: int, activo: bool}  $datos
     */
    public function actualizar(Grado $grado, array $datos): Grado
    {
        $grado->update($datos);

        return $grado;
    }

    public function existeOrden(int $orden, ?int $exceptoId = null): bool
    {
        return Grado::query()
            ->where('orden', $orden)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }
}
