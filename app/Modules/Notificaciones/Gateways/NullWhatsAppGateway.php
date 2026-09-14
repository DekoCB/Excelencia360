<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Gateways;

use App\Modules\Notificaciones\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Driver por defecto para entornos sin credenciales reales de WhatsApp
 * Business (local, testing, demo). Simula un envío exitoso y lo deja
 * registrado en el log en vez de llamar a una API externa.
 */
class NullWhatsAppGateway implements WhatsAppGateway
{
    public function enviar(string $telefono, string $mensaje): array
    {
        Log::info('[WhatsApp:null] Mensaje simulado', [
            'telefono' => $telefono,
            'mensaje' => $mensaje,
        ]);

        return [
            'exitoso' => true,
            'external_id' => 'null-'.Str::uuid(),
            'error' => null,
        ];
    }
}
