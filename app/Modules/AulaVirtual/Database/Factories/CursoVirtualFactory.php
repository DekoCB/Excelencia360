<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoVirtual>
 */
class CursoVirtualFactory extends Factory
{
    protected $model = CursoVirtual::class;

    public function definition(): array
    {
        return [
            'horario_id' => Horario::factory(),
            'activo' => true,
        ];
    }

    /**
     * Aula virtual de curso corto: anclada a un Curso (con docente a
     * cargo) en vez de a un Horario.
     */
    public function deCursoCorto(?Curso $curso = null): static
    {
        return $this->state(fn () => [
            'horario_id' => null,
            'curso_id' => ($curso ?? Curso::factory()->capacitacion()->conDocente()->create())->id,
        ]);
    }
}
