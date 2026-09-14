<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Enums;

enum EstadoEntregaEnum: string
{
    case PENDIENTE = 'pendiente';
    case ENTREGADO = 'entregado';
    case CALIFICADO = 'calificado';
    case TARDE = 'tarde';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ENTREGADO => 'Entregado',
            self::CALIFICADO => 'Calificado',
            self::TARDE => 'Entregado tarde',
        };
    }
}
