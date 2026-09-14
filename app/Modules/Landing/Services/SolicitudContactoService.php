<?php

declare(strict_types=1);

namespace App\Modules\Landing\Services;

use App\Modules\Landing\Models\SolicitudContacto;

/**
 * El envío por correo/WhatsApp automático queda pendiente de credenciales
 * de producción (ver docs/DESPLIEGUE.md); por ahora el mensaje solo se
 * persiste para que el equipo lo revise desde el panel más adelante.
 */
class SolicitudContactoService
{
    public function registrar(string $nombre, string $email, string $telefono, ?string $asunto, string $mensaje): SolicitudContacto
    {
        return SolicitudContacto::query()->create([
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'asunto' => $asunto,
            'mensaje' => $mensaje,
        ]);
    }
}
