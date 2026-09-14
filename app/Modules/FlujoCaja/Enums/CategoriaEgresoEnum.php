<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Enums;

enum CategoriaEgresoEnum: string
{
    case ALQUILER = 'alquiler';
    case SERVICIOS = 'servicios';
    case PLANILLA = 'planilla';
    case MATERIALES = 'materiales';
    case MANTENIMIENTO = 'mantenimiento';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::ALQUILER => 'Alquiler',
            self::SERVICIOS => 'Servicios (luz, agua, internet)',
            self::PLANILLA => 'Planilla',
            self::MATERIALES => 'Materiales',
            self::MANTENIMIENTO => 'Mantenimiento',
            self::OTRO => 'Otro',
        };
    }
}
