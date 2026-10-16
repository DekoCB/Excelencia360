<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum EstadoMatriculaCursoCortoEnum: string
{
    case ACTIVO = 'activo';
    case CONCLUIDO = 'concluido';
    case RETIRADO = 'retirado';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::CONCLUIDO => 'Concluido',
            self::RETIRADO => 'Retirado',
        };
    }
}
