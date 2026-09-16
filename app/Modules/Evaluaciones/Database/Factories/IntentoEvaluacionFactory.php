<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\IntentoEvaluacion;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntentoEvaluacion>
 */
class IntentoEvaluacionFactory extends Factory
{
    protected $model = IntentoEvaluacion::class;

    public function definition(): array
    {
        return [
            'evaluacion_id' => Evaluacion::factory()->virtual(),
            'estudiante_id' => Estudiante::factory(),
            'enviado_en' => now(),
        ];
    }

    public function calificado(): static
    {
        return $this->state(['calificado_en' => now()]);
    }
}
