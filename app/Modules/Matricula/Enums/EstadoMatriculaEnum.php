<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum EstadoMatriculaEnum: string
{
    case PENDIENTE = 'pendiente';
    case APROBADA = 'aprobada';
    case OBSERVADA = 'observada';
    case ANULADA = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::APROBADA => 'Aprobada',
            self::OBSERVADA => 'Observada',
            self::ANULADA => 'Anulada',
        };
    }
}
