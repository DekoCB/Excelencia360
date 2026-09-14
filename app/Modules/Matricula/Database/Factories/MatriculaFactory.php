<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matricula>
 */
class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'ciclo_id' => Ciclo::factory(),
            'grado_id' => Grado::factory(),
            'fecha_matricula' => now(),
            'estado' => EstadoMatriculaEnum::APROBADA,
        ];
    }
}
