<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Enums;

/**
 * Estados sugeridos por el documento de requerimientos (sección 23),
 * tal cual: Registrada → En revisión → (Observada) → Aprobada/Denegada →
 * Atendida → Archivada. No se fuerza ese orden como una máquina de
 * estados estricta (el responsable puede volver de Observada a En
 * revisión, por ejemplo) -- ver TramiteService::actualizarEstado().
 */
enum EstadoTramiteEnum: string
{
    case REGISTRADA = 'registrada';
    case EN_REVISION = 'en_revision';
    case OBSERVADA = 'observada';
    case APROBADA = 'aprobada';
    case DENEGADA = 'denegada';
    case ATENDIDA = 'atendida';
    case ARCHIVADA = 'archivada';

    public function label(): string
    {
        return match ($this) {
            self::REGISTRADA => 'Registrada',
            self::EN_REVISION => 'En revisión',
            self::OBSERVADA => 'Observada',
            self::APROBADA => 'Aprobada',
            self::DENEGADA => 'Denegada',
            self::ATENDIDA => 'Atendida',
            self::ARCHIVADA => 'Archivada',
        };
    }

    public function variantePildora(): string
    {
        return match ($this) {
            self::REGISTRADA => 'neutral',
            self::EN_REVISION => 'info',
            self::OBSERVADA => 'warn',
            self::APROBADA => 'ok',
            self::DENEGADA => 'danger',
            self::ATENDIDA => 'accent',
            self::ARCHIVADA => 'neutral',
        };
    }

    /**
     * Estados donde el solicitante necesita una explicación del porqué:
     * el formulario de gestión exige texto de resolución antes de guardar.
     */
    public function requiereResolucion(): bool
    {
        return match ($this) {
            self::OBSERVADA, self::APROBADA, self::DENEGADA, self::ATENDIDA => true,
            default => false,
        };
    }
}
