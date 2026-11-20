<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Database\Factories;

use App\Models\User;
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
            'user_id' => User::factory(),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addMonths(2),
        ];
    }
}
