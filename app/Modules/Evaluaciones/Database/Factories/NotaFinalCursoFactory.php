<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Models\NotaFinalCurso;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaFinalCurso>
 */
class NotaFinalCursoFactory extends Factory
{
    protected $model = NotaFinalCurso::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'horario_id' => Horario::factory(),
            'nota' => $this->faker->randomFloat(2, 0, 20),
            'editado_por' => null,
        ];
    }
}
