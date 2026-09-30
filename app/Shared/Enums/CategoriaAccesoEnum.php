<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Models\User;

/**
 * Agrupa los roles del sistema en las puertas de entrada que se ofrecen
 * en el selector de la pantalla de login: no reemplaza a RolEnum, solo
 * decide a cuál de esas tarjetas pertenece cada rol.
 *
 * El Portal de Apoderados ya no tiene puerta de entrada propia (decisión
 * del cliente): una cuenta con rol Apoderado no encaja en ninguna
 * categoría de aquí, así que ->incluyeA() la rechaza sin importar qué
 * tarjeta elija -- ver LoginForm::authenticate().
 */
enum CategoriaAccesoEnum: string
{
    case PERSONAL = 'personal';
    case ESTUDIANTE = 'estudiante';

    public function label(): string
    {
        return match ($this) {
            self::ESTUDIANTE => 'Estudiante',
            self::PERSONAL => 'Personal administrativo',
        };
    }

    /**
     * @return list<RolEnum>
     */
    public function roles(): array
    {
        return match ($this) {
            self::ESTUDIANTE => [RolEnum::ESTUDIANTE],
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
