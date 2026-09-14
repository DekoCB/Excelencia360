<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Identidad\Support\Auditable;
use App\Shared\Enums\RolEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Historial de cambios de contraseña, derivado del log de auditoría genérico
 * (ver {@see Auditable}): cada vez que
 * "password" aparece entre los campos cambiados de un User, cuenta como un
 * evento aquí. El valor real de la contraseña nunca se expone -- Auditable
 * ya lo reemplaza por un marcador antes de guardarlo.
 */
class HistorialContrasenaService
{
    public function listar(bool $soloEstudiantes, int $perPage = 15): LengthAwarePaginator
    {
        $usuarios = User::query();

        if ($soloEstudiantes) {
            $usuarios->whereHas('roles', fn ($query) => $query->where('name', RolEnum::ESTUDIANTE->value));
        } else {
            $usuarios->whereDoesntHave('roles', fn ($query) => $query->where('name', RolEnum::ESTUDIANTE->value));
        }

        return AuditLog::query()
            ->where('auditable_type', (new User)->getMorphClass())
            ->where('event', 'updated')
            ->whereNotNull('new_values->password')
            ->whereIn('auditable_id', $usuarios->pluck('id'))
            ->with('auditable')
            ->latest('created_at')
            ->paginate($perPage);
    }
}
