<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Gateways;

use App\Modules\Notificaciones\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Http;

/**
 * Driver real contra la WhatsApp Cloud API de Meta. Requiere
 * WHATSAPP_TOKEN y WHATSAPP_PHONE_ID en .env (ver config/services.php).
 */
class MetaWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $token,
        private readonly string $phoneId,
        private readonly string $apiVersion = 'v20.0',
    ) {}

    public function enviar(string $telefono, string $mensaje): array
    {
        $respuesta = Http::withToken($this->token)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($telefono, '+'),
                'type' => 'text',
                'text' => ['body' => $mensaje],
            ]);

        if ($respuesta->failed()) {
            return [
                'exitoso' => false,
                'external_id' => null,
                'error' => $respuesta->json('error.message') ?? "HTTP {$respuesta->status()}",
            ];
        }

        return [
            'exitoso' => true,
            'external_id' => $respuesta->json('messages.0.id'),
            'error' => null,
        ];
    }
}
