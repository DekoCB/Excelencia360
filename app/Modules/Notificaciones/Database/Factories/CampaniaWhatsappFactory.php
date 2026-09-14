<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Database\Factories;

use App\Models\User;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Models\CampaniaWhatsapp;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaniaWhatsapp>
 */
class CampaniaWhatsappFactory extends Factory
{
    protected $model = CampaniaWhatsapp::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Campaña '.$this->faker->words(2, true),
            'plantilla_id' => PlantillaWhatsapp::factory(),
            'segmento' => [],
            'estado' => EstadoCampaniaEnum::BORRADOR,
            'total_destinatarios' => 0,
            'enviados' => 0,
            'fallidos' => 0,
            'creado_por' => User::factory(),
        ];
    }
}
