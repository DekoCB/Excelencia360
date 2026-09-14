<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Models\ClaseGrabada;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaseGrabada>
 */
class ClaseGrabadaFactory extends Factory
{
    protected $model = ClaseGrabada::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'tipo' => TipoClaseGrabadaEnum::ENLACE,
            'titulo' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'orden' => 0,
        ];
    }
}
