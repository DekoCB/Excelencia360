<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Enums;

enum EstadoSolicitudJustificacionEnum: string
{
    case PENDIENTE = 'pendiente';
    case APROBADA = 'aprobada';
    case RECHAZADA = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
