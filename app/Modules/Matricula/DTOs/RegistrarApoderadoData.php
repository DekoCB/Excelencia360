<?php

declare(strict_types=1);

namespace App\Modules\Matricula\DTOs;

use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;

final readonly class RegistrarApoderadoData
{
    public function __construct(
        public string $nombres,
        public Dni $dni,
        public Telefono $celular,
        public ?string $correo,
        public ?string $direccion,
        public string $parentesco,
    ) {}
}
