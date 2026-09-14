<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Contracts;

use App\Models\User;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Listado paginado de usuarios, con búsqueda por nombre/email/DNI y
     * filtro opcional por rol. Usado por la pantalla de gestión de usuarios.
     */
    public function buscar(?string $termino, ?string $rol, int $perPage = 15): LengthAwarePaginator;

    public function existeEmail(string $email, ?int $exceptoId = null): bool;

    public function existeDni(string $dni, ?int $exceptoId = null): bool;
}
