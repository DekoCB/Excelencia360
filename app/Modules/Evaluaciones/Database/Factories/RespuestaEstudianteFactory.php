<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Evaluaciones\Models\Pregunta;
use App\Modules\Evaluaciones\Models\RespuestaEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RespuestaEstudiante>
 */
class RespuestaEstudianteFactory extends Factory
{
    protected $model = RespuestaEstudiante::class;

    public function definition(): array
    {
        return [
            'pregunta_id' => Pregunta::factory(),
            'estudiante_id' => Estudiante::factory(),
        ];
    }
}
