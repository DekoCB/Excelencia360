<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Enums;

enum TipoClaseGrabadaEnum: string
{
    case ARCHIVO = 'archivo';
    case ENLACE = 'enlace';

    public function label(): string
    {
        return match ($this) {
            self::ARCHIVO => 'Video subido',
            self::ENLACE => 'Enlace',
        };
    }

    public function requiereArchivo(): bool
    {
        return match ($this) {
            self::ARCHIVO => true,
            self::ENLACE => false,
        };
    }
}
