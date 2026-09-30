<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Distingue un curso del currículo regular (con semestre y horario propio)
 * de un curso de capacitación (catálogo simple, sin semestre ni horario,
 * que solo existe para poder emitir Certificado::CERTIFICADO_CAPACITACION
 * -- ver App\Modules\Certificados\Services\CertificadoService). Ambos viven
 * en la misma tabla `cursos` desde la fusión de catálogos.
 */
enum TipoCursoEnum: string
{
    case ACADEMICO = 'academico';
    case CAPACITACION = 'capacitacion';

    public function label(): string
    {
        return match ($this) {
            self::ACADEMICO => 'Académico',
            self::CAPACITACION => 'Capacitación',
        };
    }
}
