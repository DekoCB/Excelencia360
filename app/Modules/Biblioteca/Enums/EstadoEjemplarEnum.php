<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Enums;

enum EstadoEjemplarEnum: string
{
    case DISPONIBLE = 'disponible';
    case PRESTADO = 'prestado';
    case PERDIDO = 'perdido';
    case EN_REPARACION = 'en_reparacion';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::PRESTADO => 'Prestado',
            self::PERDIDO => 'Perdido',
            self::EN_REPARACION => 'En reparación',
        };
    }

    public function variantePildora(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'ok',
            self::PRESTADO => 'info',
            self::PERDIDO => 'danger',
            self::EN_REPARACION => 'warn',
        };
    }
}
