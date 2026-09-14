<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoVirtual>
 */
class CursoVirtualFactory extends Factory
{
    protected $model = CursoVirtual::class;

    public function definition(): array
    {
        return [
            'horario_id' => Horario::factory(),
            'activo' => true,
        ];
    }
}
