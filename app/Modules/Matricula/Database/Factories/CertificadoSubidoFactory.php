<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Enums\TipoCertificadoSubidoEnum;
use App\Modules\Matricula\Models\CertificadoSubido;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificadoSubido>
 */
class CertificadoSubidoFactory extends Factory
{
    protected $model = CertificadoSubido::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'tipo' => TipoCertificadoSubidoEnum::ESTUDIOS,
            'curso_id' => null,
        ];
    }
}
