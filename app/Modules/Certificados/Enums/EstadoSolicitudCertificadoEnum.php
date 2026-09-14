<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Enums;

enum EstadoSolicitudCertificadoEnum: string
{
    case PENDIENTE = 'pendiente';
    case ATENDIDA = 'atendida';
    case RECHAZADA = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ATENDIDA => 'Atendida',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
