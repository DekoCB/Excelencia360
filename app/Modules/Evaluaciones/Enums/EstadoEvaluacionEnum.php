<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Enums;

enum EstadoEvaluacionEnum: string
{
    case BORRADOR = 'borrador';
    case PUBLICADA = 'publicada';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::PUBLICADA => 'Publicada',
        };
    }
}
