<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('usuarios.ver');
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $user->hasPermissionTo('usuarios.ver');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('usuarios.crear');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionTo('usuarios.editar');
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $user->hasPermissionTo('roles.gestionar');
    }

    public function manageSessions(User $user, User $target): bool
    {
        return $user->is($target) || $user->hasPermissionTo('usuarios.gestionar_sesiones');
    }
}
