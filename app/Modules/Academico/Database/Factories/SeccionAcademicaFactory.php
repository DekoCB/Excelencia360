<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\SeccionAcademica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeccionAcademica>
 */
class SeccionAcademicaFactory extends Factory
{
    protected $model = SeccionAcademica::class;

    public function definition(): array
    {
        return [
            'letra' => $this->faker->unique()->randomLetter(),
            'activa' => true,
        ];
    }
}
