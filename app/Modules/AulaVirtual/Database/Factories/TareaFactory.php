<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarea>
 */
class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'fecha_limite' => now()->addWeek(),
            'puntaje_max' => 20,
        ];
    }

    public function vencida(): static
    {
        return $this->state(['fecha_limite' => now()->subDays(3)]);
    }
}
