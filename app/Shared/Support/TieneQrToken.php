<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Str;

/**
 * Da a un modelo (Estudiante, Docente) un token opaco y estable para su
 * carnet de asistencia por QR. Se genera perezosamente en el primer acceso
 * en vez de en la creación del modelo, así no hay que tocar cada lugar del
 * código que crea un Estudiante/Docente ni migrar datos existentes.
 *
 * Es un token aparte del DNI a propósito: el QR puede terminar en una
 * foto o capturas de pantalla, y el DNI es un dato más sensible que
 * conviene no exponer ahí.
 */
trait TieneQrToken
{
    public function obtenerOCrearQrToken(): string
    {
        if ($this->qr_token !== null) {
            return $this->qr_token;
        }

        do {
            $token = Str::random(40);
        } while (static::query()->where('qr_token', $token)->exists());

        $this->forceFill(['qr_token' => $token])->save();

        return $this->qr_token;
    }
}
