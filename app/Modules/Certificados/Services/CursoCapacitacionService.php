<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Services;

use App\Modules\Certificados\Models\CursoCapacitacion;
use Illuminate\Database\Eloquent\Collection;

class CursoCapacitacionService
{
    /**
     * @return Collection<int, CursoCapacitacion>
     */
    public function todos(): Collection
    {
        return CursoCapacitacion::query()->orderBy('nombre')->get();
    }

    public function crear(string $nombre, int $horasLectivas, ?string $documentoAutorizacion): CursoCapacitacion
    {
        return CursoCapacitacion::query()->create([
            'nombre' => $nombre,
            'horas_lectivas' => $horasLectivas,
            'documento_autorizacion' => $documentoAutorizacion,
        ]);
    }

    public function actualizar(CursoCapacitacion $curso, string $nombre, int $horasLectivas, ?string $documentoAutorizacion): CursoCapacitacion
    {
        $curso->update([
            'nombre' => $nombre,
            'horas_lectivas' => $horasLectivas,
            'documento_autorizacion' => $documentoAutorizacion,
        ]);

        return $curso;
    }

    public function eliminar(CursoCapacitacion $curso): void
    {
        $curso->delete();
    }
}
