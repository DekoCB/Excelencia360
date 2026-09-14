<?php

declare(strict_types=1);

namespace App\Modules\Identidad\DTOs;

final readonly class SesionActiva
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public int $lastActivity,
        public bool $esActual,
        public ?string $nombre = null,
    ) {}
}
