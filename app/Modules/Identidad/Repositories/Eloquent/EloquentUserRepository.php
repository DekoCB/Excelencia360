<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Eloquent;

use App\Models\User;
use App\Modules\Identidad\Repositories\Contracts\UserRepositoryInterface;
use App\Shared\Enums\RolEnum;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<User>
 */
class EloquentUserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function query(): Builder
    {
        return User::query();
    }

    public function buscar(?string $termino, ?string $rol, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', RolEnum::ESTUDIANTE->value))
            ->when($termino, fn ($query) => $query->where(function ($query) use ($termino) {
                $query->where('name', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            }))
            ->when($rol, fn ($query) => $query->whereHas('roles', fn ($query) => $query->where('name', $rol)))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function existeEmail(string $email, ?int $exceptoId = null): bool
    {
        return User::query()
            ->where('email', $email)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }

    public function existeDni(string $dni, ?int $exceptoId = null): bool
    {
        return User::query()
            ->where('dni', $dni)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }
}
