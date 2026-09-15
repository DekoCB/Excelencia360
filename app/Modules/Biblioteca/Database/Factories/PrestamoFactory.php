<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Database\Factories;

use App\Models\User;
use App\Modules\Biblioteca\Enums\EstadoPrestamoEnum;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Prestamo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestamo>
 */
class PrestamoFactory extends Factory
{
    protected $model = Prestamo::class;

    public function definition(): array
    {
        return [
            'ejemplar_id' => Ejemplar::factory(),
            'solicitante_id' => User::factory(),
            'entregado_por' => User::factory(),
            'fecha_prestamo' => now()->format('Y-m-d'),
            'fecha_devolucion_esperada' => now()->addDays(7)->format('Y-m-d'),
            'estado' => EstadoPrestamoEnum::PRESTADO,
        ];
    }

    public function conEstado(EstadoPrestamoEnum $estado): self
    {
        return $this->state(['estado' => $estado]);
    }
}
