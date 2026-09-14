<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Models\Comentario;
use App\Modules\AulaVirtual\Models\Publicacion;

class ComentarioService
{
    public function comentar(Publicacion $commentable, int $autorId, string $contenido): Comentario
    {
        return $commentable->comentarios()->create([
            'autor_id' => $autorId,
            'contenido' => $contenido,
        ]);
    }
}
