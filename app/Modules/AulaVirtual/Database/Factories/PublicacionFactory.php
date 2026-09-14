<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Models\User;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Publicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publicacion>
 */
class PublicacionFactory extends Factory
{
    protected $model = Publicacion::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'autor_id' => User::factory(),
            'tipo' => TipoPublicacionEnum::AVISO,
            'contenido' => $this->faker->paragraph(),
        ];
    }
}
