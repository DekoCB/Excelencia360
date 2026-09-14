<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Models\User;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\ForoRespuesta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForoRespuesta>
 */
class ForoRespuestaFactory extends Factory
{
    protected $model = ForoRespuesta::class;

    public function definition(): array
    {
        return [
            'foro_id' => Foro::factory(),
            'autor_id' => User::factory(),
            'contenido' => $this->faker->sentence(),
        ];
    }
}
