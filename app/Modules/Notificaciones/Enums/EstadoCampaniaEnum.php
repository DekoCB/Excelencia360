<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum EstadoCampaniaEnum: string
{
    case BORRADOR = 'borrador';
    case ENVIANDO = 'enviando';
    case COMPLETADA = 'completada';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::ENVIANDO => 'Enviando',
            self::COMPLETADA => 'Completada',
        };
    }
}
