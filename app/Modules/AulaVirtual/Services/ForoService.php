<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\ForoRespuesta;
use Illuminate\Database\Eloquent\Collection;

class ForoService
{
    public function crear(CursoVirtual $curso, int $autorId, string $titulo, ?string $descripcion, ?int $semana = null): Foro
    {
        return $curso->foros()->create([
            'autor_id' => $autorId,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'semana' => $semana,
        ]);
    }

    /**
     * Crea el mismo foro en varios cursos virtuales a la vez (ej. las
     * distintas aulas/grupos de un mismo curso).
     *
     * @param  Collection<int, CursoVirtual>  $cursos
     * @return Collection<int, Foro>
     */
    public function crearParaVarios(Collection $cursos, int $autorId, string $titulo, ?string $descripcion, ?int $semana = null): Collection
    {
        return $cursos->map(fn (CursoVirtual $curso) => $this->crear($curso, $autorId, $titulo, $descripcion, $semana));
    }

    public function responder(Foro $foro, int $autorId, string $contenido): ForoRespuesta
    {
        return $foro->respuestas()->create([
            'autor_id' => $autorId,
            'contenido' => $contenido,
        ]);
    }
}
