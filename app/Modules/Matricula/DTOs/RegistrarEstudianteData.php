<?php

declare(strict_types=1);

namespace App\Modules\Matricula\DTOs;

use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;

final readonly class RegistrarEstudianteData
{
    public function __construct(
        public string $nombres,
        public string $apellidos,
        public Dni $dni,
        public string $fechaNacimiento,
        public ?EstadoCivilEnum $estadoCivil,
        public ?string $direccion,
        public ?Telefono $celular,
        public ?string $observaciones,
    ) {}
}
