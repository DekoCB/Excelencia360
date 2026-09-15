<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Enums;

enum TipoEventoEnum: string
{
    case REUNION = 'reunion';
    case ACTO = 'acto';
    case FERIADO = 'feriado';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::REUNION => 'Reunión',
            self::ACTO => 'Acto institucional',
            self::FERIADO => 'Feriado / no lectivo',
            self::OTRO => 'Otro',
        };
    }

    public function variantePildora(): string
    {
        return match ($this) {
            self::REUNION => 'info',
            self::ACTO => 'accent',
            self::FERIADO => 'warn',
            self::OTRO => 'neutral',
        };
    }
}
