<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Policies;

use App\Models\User;
use App\Modules\Pagos\Models\Pago;

class PagoPolicy
{
    public function view(User $user, Pago $pago): bool
    {
        if ($user->hasPermissionTo('pagos.ver') || $user->hasPermissionTo('pagos.gestionar')) {
            return true;
        }

        if (! $user->hasPermissionTo('pagos.ver_propio')) {
            return false;
        }

        return $user->estudiante?->id === $pago->estudiante_id;
    }
}
