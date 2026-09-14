<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum MedioCuentaEnum: string
{
    case BANCO = 'banco';
    case BILLETERA = 'billetera';

    public function label(): string
    {
        return match ($this) {
            self::BANCO => 'Cuenta bancaria',
            self::BILLETERA => 'Billetera digital',
        };
    }
}
