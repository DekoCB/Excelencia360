<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Models\BloqueoAcceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BloqueoAcceso>
 */
class BloqueoAccesoFactory extends Factory
{
    protected $model = BloqueoAcceso::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'motivo' => '2 cuotas vencidas sin pagar',
            'fecha_bloqueo' => now()->format('Y-m-d'),
            'activo' => true,
        ];
    }
}
