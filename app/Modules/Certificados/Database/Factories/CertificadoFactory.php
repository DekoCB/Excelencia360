<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Database\Factories;

use App\Models\User;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificado>
 */
class CertificadoFactory extends Factory
{
    protected $model = Certificado::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'matricula_id' => null,
            'numero' => sprintf('%06d-%s', $this->faker->unique()->numberBetween(1, 999999), now()->format('Y')),
            'codigo_verificacion' => strtoupper($this->faker->unique()->bothify('??????????')),
            'es_duplicado' => false,
            'certificado_original_id' => null,
            'emitido_por' => User::factory(),
            'fecha_emision' => now(),
            'observaciones' => null,
        ];
    }
}
