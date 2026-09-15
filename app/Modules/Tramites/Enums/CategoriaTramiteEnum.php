<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Enums;

/**
 * Clasificación amplia del trámite, solo para poder filtrar/reportar --
 * el detalle real va en el asunto/descripción de cada solicitud (un FUT
 * es, por naturaleza, de propósito abierto: no hay un catálogo cerrado de
 * "tipos de trámite" documentado por la institución que se pueda asumir
 * sin inventarlo).
 */
enum CategoriaTramiteEnum: string
{
    case ACADEMICO = 'academico';
    case ADMINISTRATIVO = 'administrativo';
    case ECONOMICO = 'economico';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::ACADEMICO => 'Académico',
            self::ADMINISTRATIVO => 'Administrativo',
            self::ECONOMICO => 'Económico',
            self::OTRO => 'Otro',
        };
    }
}
