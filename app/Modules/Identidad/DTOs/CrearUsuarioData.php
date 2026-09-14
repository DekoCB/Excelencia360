<?php

declare(strict_types=1);

namespace App\Modules\Identidad\DTOs;

use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;

final readonly class CrearUsuarioData
{
    public function __construct(
        public string $name,
        public string $email,
        public Dni $dni,
        public ?Telefono $phone,
        public string $password,
        public RolEnum $rol,
    ) {}
}
