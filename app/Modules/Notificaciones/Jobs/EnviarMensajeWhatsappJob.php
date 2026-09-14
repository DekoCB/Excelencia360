<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Jobs;

use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Notificaciones\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class EnviarMensajeWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly MensajeWhatsapp $mensaje,
    ) {}

    /**
     * El envío real por WhatsApp está pausado por ahora: en vez de llamar al
     * WhatsAppGateway, el mensaje se refleja como una notificación in-app
     * para que el estudiante lo vea en "Mis mensajes" y en la campana.
     */
    public function handle(NotificacionService $notificaciones): void
    {
        $usuario = $this->mensaje->estudiante?->user;
        $exitoso = $usuario !== null;

        if ($usuario) {
            $notificaciones->notificar(
                $usuario,
                TipoNotificacionEnum::MENSAJE,
                Str::limit($this->mensaje->contenido, 120),
                route('notificaciones.mis-mensajes'),
            );

            $this->mensaje->update([
                'estado' => EstadoMensajeWhatsappEnum::ENVIADO,
                'enviado_en' => now(),
            ]);
        } else {
            $this->mensaje->update([
                'estado' => EstadoMensajeWhatsappEnum::FALLIDO,
                'error' => 'El estudiante no tiene una cuenta de usuario vinculada.',
            ]);
        }

        $campania = $this->mensaje->campania;

        if (! $campania) {
            return;
        }

        $campania->increment($exitoso ? 'enviados' : 'fallidos');
        $campania->refresh();

        if ($campania->enviados + $campania->fallidos >= $campania->total_destinatarios) {
            $campania->update(['estado' => EstadoCampaniaEnum::COMPLETADA]);
        }
    }
}
