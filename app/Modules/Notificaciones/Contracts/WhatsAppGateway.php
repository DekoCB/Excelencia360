<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Contracts;

interface WhatsAppGateway
{
    /**
     * Envía un mensaje de texto libre al número indicado (formato E.164,
     * ej. +51987654321).
     *
     * @return array{exitoso: bool, external_id: ?string, error: ?string}
     */
    public function enviar(string $telefono, string $mensaje): array;
}
