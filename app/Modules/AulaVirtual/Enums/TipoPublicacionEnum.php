<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Enums;

enum TipoPublicacionEnum: string
{
    case AVISO = 'aviso';
    case ANUNCIO = 'anuncio';

    public function label(): string
    {
        return match ($this) {
            self::AVISO => 'Aviso',
            self::ANUNCIO => 'Anuncio',
        };
    }
}
