<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

enum EstadoPeriodoMatriculaEnum: string
{
    case ABIERTO = 'abierto';
    case CERRADO = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::ABIERTO => 'Abierto',
            self::CERRADO => 'Cerrado',
        };
    }
}
