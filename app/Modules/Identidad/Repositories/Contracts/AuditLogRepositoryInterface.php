<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Contracts;

use App\Modules\Identidad\Models\AuditLog;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RepositoryInterface<AuditLog>
 */
interface AuditLogRepositoryInterface extends RepositoryInterface
{
    /**
     * Historial de auditoría de un registro concreto, más reciente primero.
     *
     * @return Collection<int, AuditLog>
     */
    public function paraModelo(Model $modelo): Collection;

    /**
     * Listado paginado para la pantalla de Auditoría, con filtro opcional
     * por tipo de evento (created/updated/deleted) y por tipo de modelo.
     */
    public function buscar(?string $evento, ?string $tipoModelo, int $perPage = 20): LengthAwarePaginator;

    /**
     * @return list<string>
     */
    public function tiposDeModeloAuditados(): array;
}
