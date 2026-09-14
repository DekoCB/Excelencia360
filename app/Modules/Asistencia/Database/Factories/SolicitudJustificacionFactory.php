<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Database\Factories;

use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Enums\EstadoSolicitudJustificacionEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Models\SolicitudJustificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudJustificacion>
 */
class SolicitudJustificacionFactory extends Factory
{
    protected $model = SolicitudJustificacion::class;

    public function definition(): array
    {
        return [
            'asistencia_id' => Asistencia::factory()->state(['estado' => EstadoAsistenciaEnum::FALTA]),
            'motivo' => 'Cita médica',
            'estado' => EstadoSolicitudJustificacionEnum::PENDIENTE,
        ];
    }
}
