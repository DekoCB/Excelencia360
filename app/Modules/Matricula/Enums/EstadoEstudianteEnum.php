<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum EstadoEstudianteEnum: string
{
    case ACTIVO = 'activo';
    case RETIRADO = 'retirado';
    case EGRESADO = 'egresado';
    case PAUSA = 'pausa';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::RETIRADO => 'Retirado',
            self::EGRESADO => 'Egresado',
            self::PAUSA => 'Pausa temporal',
        };
    }
}
