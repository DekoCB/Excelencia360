<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

enum EstadoCicloEnum: string
{
    case PLANIFICADO = 'planificado';
    case ACTIVO = 'activo';
    case CERRADO = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::PLANIFICADO => 'Planificado',
            self::ACTIVO => 'Activo',
            self::CERRADO => 'Cerrado',
        };
    }
}
