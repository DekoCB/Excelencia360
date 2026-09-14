<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Enums;

/**
 * Los documentos que un estudiante puede solicitar. La libreta de notas
 * se genera distinto (un resumen de promedios, no una plantilla de
 * texto) pero comparte el mismo flujo de solicitud, revisión y entrega
 * que los demás -- ver CertificadoService::emitirLibretaDesdeSolicitud().
 */
enum TipoDocumentoEnum: string
{
    case CERTIFICADO_ESTUDIOS = 'certificado_estudios';
    case CONSTANCIA_ESTUDIOS = 'constancia_estudios';
    /** @deprecated Ya no se ofrece en el módulo Constancias (ver constancias() abajo); se conserva solo para no romper el casteo de registros antiguos que ya la usan. */
    case CONSTANCIA_VACANTE = 'constancia_vacante';
    case CONSTANCIA_BUENA_CONDUCTA = 'constancia_buena_conducta';
    case CONSTANCIA_MATRICULA = 'constancia_matricula';
    case CONSTANCIA_EGRESADO = 'constancia_egresado';
    case LIBRETA_NOTAS = 'libreta_notas';

    public function label(): string
    {
        return match ($this) {
            self::CERTIFICADO_ESTUDIOS => 'Certificado de estudios',
            self::CONSTANCIA_ESTUDIOS => 'Constancia de estudios',
            self::CONSTANCIA_VACANTE => 'Constancia de vacante',
            self::CONSTANCIA_BUENA_CONDUCTA => 'Constancia de buena conducta',
            self::CONSTANCIA_MATRICULA => 'Constancia de matrícula',
            self::CONSTANCIA_EGRESADO => 'Constancia de egresado',
            self::LIBRETA_NOTAS => 'Libreta de notas',
        };
    }

    public function esLibreta(): bool
    {
        return $this === self::LIBRETA_NOTAS;
    }

    /**
     * @return list<self>
     */
    public static function conPlantilla(): array
    {
        return array_filter(self::cases(), fn (self $tipo) => ! $tipo->esLibreta());
    }

    public function esConstancia(): bool
    {
        return in_array($this, self::constancias(), true);
    }

    /**
     * Documentos que se solicitan/emiten desde el módulo Certificados: el
     * certificado de estudios propiamente dicho y la libreta de notas (que
     * comparte el mismo flujo de solicitud/entrega aunque no sea un
     * certificado en sentido estricto -- nunca fue una constancia).
     *
     * @return list<self>
     */
    public static function certificados(): array
    {
        return [self::CERTIFICADO_ESTUDIOS, self::LIBRETA_NOTAS];
    }

    /**
     * Documentos que se solicitan/emiten desde el módulo Constancias.
     * CONSTANCIA_VACANTE se dejó de ofrecer aquí (ver caso arriba: el valor
     * del enum se conserva para no romper certificados/solicitudes/plantillas
     * ya emitidos con ese tipo, solo se retiró de esta lista).
     *
     * @return list<self>
     */
    public static function constancias(): array
    {
        return [self::CONSTANCIA_ESTUDIOS, self::CONSTANCIA_MATRICULA, self::CONSTANCIA_EGRESADO, self::CONSTANCIA_BUENA_CONDUCTA];
    }
}
