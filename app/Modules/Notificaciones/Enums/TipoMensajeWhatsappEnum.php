<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum TipoMensajeWhatsappEnum: string
{
    case CAMPANIA = 'campania';
    case RECORDATORIO = 'recordatorio';
    case ENTRANTE = 'entrante';
    case INCIDENCIA = 'incidencia';

    public function label(): string
    {
        return match ($this) {
            self::CAMPANIA => 'Campaña',
            self::RECORDATORIO => 'Recordatorio automático',
            self::ENTRANTE => 'Recibido',
            self::INCIDENCIA => 'Incidencia',
        };
    }
}
