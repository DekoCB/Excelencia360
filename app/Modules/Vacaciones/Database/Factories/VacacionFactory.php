<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Vacaciones\Models\Vacacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacacion>
 */
class VacacionFactory extends Factory
{
    protected $model = Vacacion::class;

    public function definition(): array
    {
        $inicio = now()->subDays(10);

        return [
            'estudiante_id' => Estudiante::factory(),
            'matricula_id' => Matricula::factory(),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addMonths(2),
        ];
    }
}
