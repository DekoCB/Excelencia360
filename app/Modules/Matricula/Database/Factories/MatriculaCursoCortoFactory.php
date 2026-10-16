<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Academico\Models\Curso;
use App\Modules\Matricula\Enums\EstadoMatriculaCursoCortoEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatriculaCursoCorto>
 */
class MatriculaCursoCortoFactory extends Factory
{
    protected $model = MatriculaCursoCorto::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'curso_id' => Curso::factory()->capacitacion(),
            'fecha_inicio' => now(),
            'estado' => EstadoMatriculaCursoCortoEnum::ACTIVO,
        ];
    }
}
