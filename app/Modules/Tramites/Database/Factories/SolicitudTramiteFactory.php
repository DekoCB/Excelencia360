<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Database\Factories;

use App\Models\User;
use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Enums\EstadoTramiteEnum;
use App\Modules\Tramites\Models\SolicitudTramite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudTramite>
 */
class SolicitudTramiteFactory extends Factory
{
    protected $model = SolicitudTramite::class;

    public function definition(): array
    {
        return [
            'solicitante_id' => User::factory(),
            'categoria' => $this->faker->randomElement(CategoriaTramiteEnum::cases()),
            'asunto' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'estado' => EstadoTramiteEnum::REGISTRADA,
        ];
    }

    public function conEstado(EstadoTramiteEnum $estado): self
    {
        return $this->state(['estado' => $estado]);
    }
}
