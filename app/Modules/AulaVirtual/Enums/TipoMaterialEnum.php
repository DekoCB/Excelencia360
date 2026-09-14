<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Enums;

enum TipoMaterialEnum: string
{
    case VIDEO = 'video';
    case PDF = 'pdf';
    case ARCHIVO = 'archivo';
    case ENLACE = 'enlace';

    public function label(): string
    {
        return match ($this) {
            self::VIDEO => 'Video',
            self::PDF => 'PDF',
            self::ARCHIVO => 'Archivo',
            self::ENLACE => 'Enlace',
        };
    }

    public function requiereArchivo(): bool
    {
        return match ($this) {
            self::PDF, self::ARCHIVO => true,
            self::VIDEO, self::ENLACE => false,
        };
    }
}
