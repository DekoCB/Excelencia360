<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Database\Factories;

use App\Models\User;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incidencia>
 */
class IncidenciaFactory extends Factory
{
    protected $model = Incidencia::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'reportado_por' => User::factory(),
            'tipo' => TipoIncidenciaEnum::CONDUCTA,
            'descripcion' => $this->faker->sentence(),
            'fecha' => now()->format('Y-m-d'),
        ];
    }
}
