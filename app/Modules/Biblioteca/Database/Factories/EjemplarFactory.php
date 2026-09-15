<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Database\Factories;

use App\Modules\Biblioteca\Enums\EstadoEjemplarEnum;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ejemplar>
 */
class EjemplarFactory extends Factory
{
    protected $model = Ejemplar::class;

    public function definition(): array
    {
        return [
            'libro_id' => Libro::factory(),
            'codigo_inventario' => 'BIB-'.$this->faker->unique()->numerify('#####'),
            'estado' => EstadoEjemplarEnum::DISPONIBLE,
        ];
    }

    public function conEstado(EstadoEjemplarEnum $estado): self
    {
        return $this->state(['estado' => $estado]);
    }
}
