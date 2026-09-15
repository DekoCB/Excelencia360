<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaDocentes\Database\Factories;

use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Models\AsistenciaDocente;
use App\Modules\Docentes\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsistenciaDocente>
 */
class AsistenciaDocenteFactory extends Factory
{
    protected $model = AsistenciaDocente::class;

    public function definition(): array
    {
        return [
            'docente_id' => Docente::factory(),
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::PRESENTE,
        ];
    }

    public function conEstado(EstadoAsistenciaEnum $estado): self
    {
        return $this->state(['estado' => $estado]);
    }
}
