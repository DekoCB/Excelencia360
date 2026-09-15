<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Database\Factories;

use App\Modules\Calendario\Enums\TipoEventoEnum;
use App\Modules\Calendario\Models\EventoCalendario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoCalendario>
 */
class EventoCalendarioFactory extends Factory
{
    protected $model = EventoCalendario::class;

    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'descripcion' => $this->faker->optional()->paragraph(),
            'tipo' => $this->faker->randomElement(TipoEventoEnum::cases()),
            'fecha_inicio' => $this->faker->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
        ];
    }
}
