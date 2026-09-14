<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Models\User;
use App\Modules\AulaVirtual\Models\Comentario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comentario>
 */
class ComentarioFactory extends Factory
{
    protected $model = Comentario::class;

    public function definition(): array
    {
        return [
            'autor_id' => User::factory(),
            'contenido' => $this->faker->sentence(),
        ];
    }
}
