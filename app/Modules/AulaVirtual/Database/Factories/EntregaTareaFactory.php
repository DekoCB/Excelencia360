<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Factories;

use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\AulaVirtual\Models\EntregaTarea;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntregaTarea>
 */
class EntregaTareaFactory extends Factory
{
    protected $model = EntregaTarea::class;

    public function definition(): array
    {
        return [
            'tarea_id' => Tarea::factory(),
            'estudiante_id' => Estudiante::factory(),
            'comentario' => $this->faker->sentence(),
            'fecha_entrega' => now(),
            'estado' => EstadoEntregaEnum::ENTREGADO,
        ];
    }
}
