<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum TipoBilleteraEnum: string
{
    case YAPE = 'yape';
    case PLIN = 'plin';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::YAPE => 'Yape',
            self::PLIN => 'Plin',
            self::OTRO => 'Otro',
        };
    }
}
