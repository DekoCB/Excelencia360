<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Database\Factories;

use App\Models\User;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Models\Notificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacion>
 */
class NotificacionFactory extends Factory
{
    protected $model = Notificacion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo' => TipoNotificacionEnum::TAREA_CALIFICADA,
            'titulo' => $this->faker->sentence(),
            'url' => null,
            'leida_en' => null,
        ];
    }

    public function leida(): static
    {
        return $this->state(['leida_en' => now()]);
    }
}
