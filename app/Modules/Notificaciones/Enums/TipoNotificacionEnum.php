<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum TipoNotificacionEnum: string
{
    case TAREA_CALIFICADA = 'tarea_calificada';
    case EVALUACION_PUBLICADA = 'evaluacion_publicada';
    case MENSAJE = 'mensaje';
    case CERTIFICADO_LISTO = 'certificado_listo';
    case TRAMITE_ATENDIDO = 'tramite_atendido';

    public function label(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'Tarea calificada',
            self::EVALUACION_PUBLICADA => 'Evaluación publicada',
            self::MENSAJE => 'Mensaje',
            self::CERTIFICADO_LISTO => 'Certificado listo',
            self::TRAMITE_ATENDIDO => 'Trámite atendido',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'clipboard-document-check',
            self::EVALUACION_PUBLICADA => 'pencil-square',
            self::MENSAJE => 'chat-bubble-left-right',
            self::CERTIFICADO_LISTO => 'document-check',
            self::TRAMITE_ATENDIDO => 'inbox-arrow-down',
        };
    }
}
