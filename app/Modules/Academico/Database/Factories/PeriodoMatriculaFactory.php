<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\PeriodoMatricula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodoMatricula>
 */
class PeriodoMatriculaFactory extends Factory
{
    protected $model = PeriodoMatricula::class;

    public function definition(): array
    {
        return [
            'ciclo_id' => Ciclo::factory(),
            'fecha_inicio' => now()->subDays(15),
            'fecha_fin' => now()->addDays(5),
            'estado' => 'abierto',
        ];
    }
}
