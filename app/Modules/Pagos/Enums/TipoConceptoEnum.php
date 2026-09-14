<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum TipoConceptoEnum: string
{
    case MATRICULA = 'matricula';
    case MENSUALIDAD = 'mensualidad';
    case CERTIFICADO = 'certificado';
    case CONSTANCIA = 'constancia';
    case PENALIDAD = 'penalidad';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::MATRICULA => 'Matrícula',
            self::MENSUALIDAD => 'Mensualidad',
            self::CERTIFICADO => 'Certificado',
            self::CONSTANCIA => 'Constancia',
            self::PENALIDAD => 'Penalidad',
            self::OTRO => 'Otro',
        };
    }
}
