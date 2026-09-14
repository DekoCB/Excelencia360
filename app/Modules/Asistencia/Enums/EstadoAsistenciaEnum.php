<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Enums;

enum EstadoAsistenciaEnum: string
{
    case PRESENTE = 'presente';
    case TARDANZA = 'tardanza';
    case FALTA = 'falta';
    case JUSTIFICADO = 'justificado';

    public function label(): string
    {
        return match ($this) {
            self::PRESENTE => 'Presente',
            self::TARDANZA => 'Tardanza',
            self::FALTA => 'Falta',
            self::JUSTIFICADO => 'Justificado',
        };
    }

    public function cuentaComoAsistio(): bool
    {
        return match ($this) {
            self::PRESENTE, self::TARDANZA, self::JUSTIFICADO => true,
            self::FALTA => false,
        };
    }
}
