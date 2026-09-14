<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Listeners;

use App\Models\User;
use App\Modules\Identidad\Jobs\RegistrarAuditoriaJob;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Deja rastro en auditoría de cada intento de login fallido — relevante
 * para detectar fuerza bruta o enumeración de cuentas. Se dispara desde
 * Auth::attempt() dentro de LoginForm::authenticate(), sin importar si el
 * email pertenece a un usuario real.
 */
class RegistrarIntentoFallidoListener
{
    public function handle(Failed $event): void
    {
        $usuarioId = $event->user instanceof User ? $event->user->id : 0;
        $email = $event->credentials['email'] ?? null;

        RegistrarAuditoriaJob::dispatch(
            null,
            'login_failed',
            User::class,
            $usuarioId,
            [],
            ['email' => $email],
            Request::ip(),
            substr((string) Request::userAgent(), 0, 255),
        );

        Log::channel('seguridad')->warning('Intento de login fallido', [
            'email' => $email,
            'usuario_id' => $usuarioId ?: null,
            'ip' => Request::ip(),
        ]);
    }
}
