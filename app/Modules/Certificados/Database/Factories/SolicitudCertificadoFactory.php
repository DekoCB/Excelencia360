<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Database\Factories;

use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudCertificado>
 */
class SolicitudCertificadoFactory extends Factory
{
    protected $model = SolicitudCertificado::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'matricula_id' => null,
            'motivo' => $this->faker->sentence(),
            'estado' => EstadoSolicitudCertificadoEnum::PENDIENTE,
            'atendido_por' => null,
            'certificado_id' => null,
            'motivo_rechazo' => null,
        ];
    }
}
