<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Jobs;

use App\Modules\Identidad\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Persiste una entrada de auditoría en cola de baja prioridad: nunca debe
 * retrasar la respuesta al usuario que originó el cambio.
 */
class RegistrarAuditoriaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $valoresAnteriores
     * @param  array<string, mixed>  $valoresNuevos
     */
    public function __construct(
        private readonly ?int $userId,
        private readonly string $evento,
        private readonly string $auditableType,
        private readonly int|string $auditableId,
        private readonly array $valoresAnteriores,
        private readonly array $valoresNuevos,
        private readonly ?string $ip,
        private readonly ?string $userAgent,
    ) {
        $this->onQueue('auditoria');
    }

    public function handle(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->userId,
            'event' => $this->evento,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'old_values' => $this->valoresAnteriores ?: null,
            'new_values' => $this->valoresNuevos ?: null,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
        ]);
    }
}
