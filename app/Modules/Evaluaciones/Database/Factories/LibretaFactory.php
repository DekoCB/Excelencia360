<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Database\Factories;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Libreta>
 */
class LibretaFactory extends Factory
{
    protected $model = Libreta::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'ciclo_id' => Ciclo::factory(),
            'generado_en' => now(),
        ];
    }
}
