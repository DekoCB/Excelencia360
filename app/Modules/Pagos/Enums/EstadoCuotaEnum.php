<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum EstadoCuotaEnum: string
{
    case PENDIENTE = 'pendiente';
    case PAGADO = 'pagado';
    case EXONERADO = 'exonerado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PAGADO => 'Pagado',
            self::EXONERADO => 'Exonerado',
        };
    }
}
