<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum RolEnum: string
{
    case DIRECCION = 'direccion';
    case COORDINADOR = 'coordinador';
    case ADMINISTRATIVO = 'administrativo';
    case TESORERIA = 'tesoreria';
    case DOCENTE = 'docente';
    case ESTUDIANTE = 'estudiante';

    public function label(): string
    {
        return match ($this) {
            self::DIRECCION => 'Dirección',
            self::COORDINADOR => 'Coordinador',
            self::ADMINISTRATIVO => 'Administrativo',
            self::TESORERIA => 'Tesorería',
            self::DOCENTE => 'Docente',
            self::ESTUDIANTE => 'Estudiante',
        };
    }
}
