<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Identidad\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * Registra un evento de auditoría sobre un modelo. Se llama desde el
     * trait {@see Auditable} en los eventos
     * created/updated/deleted de cualquier modelo que lo use.
     *
     * @param  array<string, mixed>  $valoresAnteriores
     * @param  array<string, mixed>  $valoresNuevos
     */
    public function registrar(Model $modelo, string $evento, array $valoresAnteriores = [], array $valoresNuevos = []): void
    {
        $this->auditLogs->create([
            'user_id' => Auth::id(),
            'event' => $evento,
            'auditable_type' => $modelo->getMorphClass(),
            'auditable_id' => $modelo->getKey(),
            'old_values' => $valoresAnteriores ?: null,
            'new_values' => $valoresNuevos ?: null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function historialDe(Model $modelo): Collection
    {
        return $this->auditLogs->paraModelo($modelo);
    }

    public function listar(?string $evento, ?string $tipoModelo, int $perPage = 20): LengthAwarePaginator
    {
        return $this->auditLogs->buscar($evento, $tipoModelo, $perPage);
    }

    /**
     * @return list<string>
     */
    public function tiposDeModeloAuditados(): array
    {
        return $this->auditLogs->tiposDeModeloAuditados();
    }
}
