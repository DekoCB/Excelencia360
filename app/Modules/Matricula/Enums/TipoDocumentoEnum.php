<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

enum TipoDocumentoEnum: string
{
    case DNI_ESTUDIANTE = 'dni_estudiante';
    case DNI_APODERADO = 'dni_apoderado';
    case CERTIFICADO_ESTUDIOS = 'certificado_estudios';
    case CONSTANCIA = 'constancia';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::DNI_ESTUDIANTE => 'DNI del estudiante',
            self::DNI_APODERADO => 'DNI del apoderado',
            self::CERTIFICADO_ESTUDIOS => 'Certificado de estudios',
            self::CONSTANCIA => 'Constancia',
            self::OTRO => 'Otro documento',
        };
    }
}
