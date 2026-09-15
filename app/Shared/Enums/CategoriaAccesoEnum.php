<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Models\User;

/**
 * Agrupa los 7 roles del sistema en las puertas de entrada que se ofrecen
 * en el selector de la pantalla de login: no reemplaza a RolEnum, solo
 * decide a cuál de esas tarjetas pertenece cada rol.
 */
enum CategoriaAccesoEnum: string
{
    case PERSONAL = 'personal';
    case ESTUDIANTE = 'estudiante';
    case APODERADO = 'apoderado';

    public function label(): string
    {
        return match ($this) {
            self::ESTUDIANTE => 'Estudiante',
            self::PERSONAL => 'Personal administrativo',
            self::APODERADO => 'Apoderado',
        };
    }

    /**
     * @return list<RolEnum>
     */
    public function roles(): array
    {
        return match ($this) {
            self::ESTUDIANTE => [RolEnum::ESTUDIANTE],
            self::APODERADO => [RolEnum::APODERADO],
            self::PERSONAL => [
                RolEnum::DIRECCION,
                RolEnum::COORDINADOR,
                RolEnum::ADMINISTRATIVO,
                RolEnum::TESORERIA,
                RolEnum::DOCENTE,
            ],
        };
    }

    public function incluyeA(User $user): bool
    {
        return $user->hasAnyRole(array_map(fn (RolEnum $rol) => $rol->value, $this->roles()));
    }
}
