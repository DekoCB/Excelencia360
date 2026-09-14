<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Cómo el estudiante eligió recibir un documento (certificado, constancia
 * o libreta) al solicitarlo: en la institución, por correo, o ambos.
 */
enum MetodoEntregaEnum: string
{
    case FISICA = 'fisica';
    case VIRTUAL = 'virtual';
    case AMBOS = 'ambos';

    public function label(): string
    {
        return match ($this) {
            self::FISICA => 'Recojo en la institución',
            self::VIRTUAL => 'Envío digital',
            self::AMBOS => 'Recojo en la institución y envío digital',
        };
    }

    public function requiereCorreo(): bool
    {
        return $this !== self::FISICA;
    }

    public function requiereRecojo(): bool
    {
        return $this !== self::VIRTUAL;
    }
}
