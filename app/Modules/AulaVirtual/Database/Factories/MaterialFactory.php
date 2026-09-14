<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'curso_virtual_id' => CursoVirtual::factory(),
            'tipo' => TipoMaterialEnum::ENLACE,
            'titulo' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'orden' => 0,
        ];
    }
}
