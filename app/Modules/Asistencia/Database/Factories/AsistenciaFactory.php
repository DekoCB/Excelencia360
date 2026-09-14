<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Database\Factories;

use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asistencia>
 */
class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    public function definition(): array
    {
        return [
            'horario_id' => Horario::factory(),
            'estudiante_id' => Estudiante::factory(),
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::PRESENTE,
            'observacion' => null,
        ];
    }
}
