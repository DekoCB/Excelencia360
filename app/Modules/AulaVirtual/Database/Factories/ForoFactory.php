<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Models\User;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Foro>
 */
class ForoFactory extends Factory
{
    protected $model = Foro::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'autor_id' => User::factory(),
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->sentence(),
        ];
    }
}
