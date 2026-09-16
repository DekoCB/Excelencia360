<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Enums;

enum TipoEvaluacionEnum: string
{
    case FISICO = 'fisico';
    case VIRTUAL = 'virtual';

    public function label(): string
    {
        return match ($this) {
            self::FISICO => 'Físico',
            self::VIRTUAL => 'Virtual',
        };
    }
}
