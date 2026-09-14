<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum EstadoMensajeWhatsappEnum: string
{
    case PENDIENTE = 'pendiente';
    case ENVIADO = 'enviado';
    case ENTREGADO = 'entregado';
    case LEIDO = 'leido';
    case FALLIDO = 'fallido';
    case RECIBIDO = 'recibido';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ENVIADO => 'Enviado',
            self::ENTREGADO => 'Entregado',
            self::LEIDO => 'Leído',
            self::FALLIDO => 'Fallido',
            self::RECIBIDO => 'Recibido',
        };
    }
}
