<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Enums;

enum EstadoPrestamoEnum: string
{
    case PRESTADO = 'prestado';
    case DEVUELTO = 'devuelto';
    case PERDIDO = 'perdido';

    public function label(): string
    {
        return match ($this) {
            self::PRESTADO => 'Prestado',
            self::DEVUELTO => 'Devuelto',
            self::PERDIDO => 'Perdido',
        };
    }

    public function variantePildora(): string
    {
        return match ($this) {
            self::PRESTADO => 'info',
            self::DEVUELTO => 'ok',
            self::PERDIDO => 'danger',
        };
    }
}
