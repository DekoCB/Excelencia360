<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum TipoCertificadoSubidoEnum: string
{
    case CAPACITACION = 'capacitacion';
    case ESTUDIOS = 'estudios';

    public function label(): string
    {
        return match ($this) {
            self::CAPACITACION => 'Certificado de capacitación',
            self::ESTUDIOS => 'Certificado de estudios',
        };
    }
}
