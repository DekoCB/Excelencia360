<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\ForoRespuesta;
use App\Modules\AulaVirtual\Models\Seccion;
use Illuminate\Database\Eloquent\Collection;

class ForoService
{
    public function __construct(private readonly SeccionService $secciones) {}

    public function crear(CursoVirtual $curso, int $autorId, string $titulo, ?string $descripcion, ?int $seccionId = null): Foro
    {
        return $curso->foros()->create([
            'autor_id' => $autorId,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'seccion_id' => $seccionId,
        ]);
    }

    /**
     * Crea el mismo foro en varios cursos virtuales a la vez (ej. las
     * distintas aulas/grupos de un mismo curso).
     *
     * @param  Collection<int, CursoVirtual>  $cursos
     * @return Collection<int, Foro>
     */
    public function crearParaVarios(Collection $cursos, int $autorId, string $titulo, ?string $descripcion, ?Seccion $seccion = null): Collection
    {
        return $cursos->map(function (CursoVirtual $curso) use ($autorId, $titulo, $descripcion, $seccion) {
            $seccionEquivalente = $this->secciones->obtenerOCrearEquivalente($curso, $seccion);

            return $this->crear($curso, $autorId, $titulo, $descripcion, $seccionEquivalente?->id);
        });
    }

    public function responder(Foro $foro, int $autorId, string $contenido): ForoRespuesta
    {
        return $foro->respuestas()->create([
            'autor_id' => $autorId,
            'contenido' => $contenido,
        ]);
    }
}
