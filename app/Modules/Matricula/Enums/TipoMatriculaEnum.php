<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

/**
 * Regular: matrícula normal, secuencial. Convalidación: para estudiantes
 * con estudios truncos en otra institución (o en esta, hace tiempo) que
 * retoman directamente en el semestre que les corresponde -- es solo una
 * etiqueta descriptiva, no cambia ninguna regla de matriculación (el
 * sistema ya permite elegir cualquier Grado libremente).
 */
enum TipoMatriculaEnum: string
{
    case REGULAR = 'regular';
    case CONVALIDACION = 'convalidacion';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::CONVALIDACION => 'Convalidación',
        };
    }
}
