<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * La franja del día en la que ocurre un turno -- elegida a mano por la
 * persona (tesorería al abrir un turno de caja, o quien registra el
 * ingreso/salida en Asistencia General), no auto-detectada por la hora
 * del reloj.
 */
enum TurnoDiaEnum: string
{
    case MANANA = 'manana';
    case TARDE = 'tarde';
    case NOCHE = 'noche';

    public function label(): string
    {
        return match ($this) {
            self::MANANA => 'Mañana',
            self::TARDE => 'Tarde',
            self::NOCHE => 'Noche',
        };
    }
}
