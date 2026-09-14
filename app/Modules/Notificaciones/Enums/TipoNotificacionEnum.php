<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum TipoNotificacionEnum: string
{
    case TAREA_CALIFICADA = 'tarea_calificada';
    case EVALUACION_PUBLICADA = 'evaluacion_publicada';
    case MENSAJE = 'mensaje';
    case CERTIFICADO_LISTO = 'certificado_listo';

    public function label(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'Tarea calificada',
            self::EVALUACION_PUBLICADA => 'Evaluación publicada',
            self::MENSAJE => 'Mensaje',
            self::CERTIFICADO_LISTO => 'Certificado listo',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'clipboard-document-check',
            self::EVALUACION_PUBLICADA => 'pencil-square',
            self::MENSAJE => 'chat-bubble-left-right',
            self::CERTIFICADO_LISTO => 'document-check',
        };
    }
}
