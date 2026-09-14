<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionService
{
    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permisos(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    /**
     * Matriz rol → lista de nombres de permiso concedidos, para pintar los
     * checkboxes de la UI de administración de roles.
     *
     * @return array<string, list<string>>
     */
    public function matriz(): array
    {
        return $this->roles()
            ->mapWithKeys(fn (Role $role) => [
                $role->name => $role->permissions->pluck('name')->all(),
            ])
            ->all();
    }

    public function otorgar(Role $role, string $permiso): void
    {
        $role->givePermissionTo($permiso);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function revocar(Role $role, string $permiso): void
    {
        $role->revokePermissionTo($permiso);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
