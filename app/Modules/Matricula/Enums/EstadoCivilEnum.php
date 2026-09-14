<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum EstadoCivilEnum: string
{
    case SOLTERO = 'soltero';
    case CASADO = 'casado';
    case CONVIVIENTE = 'conviviente';
    case DIVORCIADO = 'divorciado';
    case VIUDO = 'viudo';

    public function label(): string
    {
        return match ($this) {
            self::SOLTERO => 'Soltero(a)',
            self::CASADO => 'Casado(a)',
            self::CONVIVIENTE => 'Conviviente',
            self::DIVORCIADO => 'Divorciado(a)',
            self::VIUDO => 'Viudo(a)',
        };
    }
}
