<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Models\User;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Models\Notificacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class NotificacionService
{
    public function notificar(User $user, TipoNotificacionEnum $tipo, string $titulo, ?string $url = null): Notificacion
    {
        return Notificacion::query()->create([
            'user_id' => $user->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'url' => $url,
        ]);
    }

    /**
     * @param  SupportCollection<int, User>|Collection<int, User>  $usuarios
     */
    public function notificarVarios(iterable $usuarios, TipoNotificacionEnum $tipo, string $titulo, ?string $url = null): void
    {
        foreach ($usuarios as $usuario) {
            $this->notificar($usuario, $tipo, $titulo, $url);
        }
    }

    /**
     * Las últimas notificaciones del usuario (leídas y no leídas, para la
     * campana del encabezado), más recientes primero.
     *
     * @return Collection<int, Notificacion>
     */
    public function recientes(User $user, int $limite = 15): Collection
    {
        return Notificacion::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limite)
            ->get();
    }

    public function contarNoLeidas(User $user): int
    {
        return Notificacion::query()
            ->where('user_id', $user->id)
            ->whereNull('leida_en')
            ->count();
    }

    public function marcarLeida(Notificacion $notificacion): void
    {
        if ($notificacion->leida_en === null) {
            $notificacion->update(['leida_en' => now()]);
        }
    }

    public function marcarTodasLeidas(User $user): void
    {
        Notificacion::query()
            ->where('user_id', $user->id)
            ->whereNull('leida_en')
            ->update(['leida_en' => now()]);
    }
}
