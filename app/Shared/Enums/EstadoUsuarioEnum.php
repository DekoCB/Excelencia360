<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum EstadoUsuarioEnum: string
{
    case ACTIVO = 'activo';
    case INACTIVO = 'inactivo';
    case SUSPENDIDO = 'suspendido';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::INACTIVO => 'Inactivo',
            self::SUSPENDIDO => 'Suspendido',
        };
    }
}
