<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Publicacion;

class PublicacionService
{
    public function crear(CursoVirtual $curso, int $autorId, TipoPublicacionEnum $tipo, string $contenido): Publicacion
    {
        return $curso->publicaciones()->create([
            'autor_id' => $autorId,
            'tipo' => $tipo,
            'contenido' => $contenido,
        ]);
    }
}
