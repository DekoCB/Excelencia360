<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Database\Factories;

use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MensajeWhatsapp>
 */
class MensajeWhatsappFactory extends Factory
{
    protected $model = MensajeWhatsapp::class;

    public function definition(): array
    {
        return [
            'campania_id' => null,
            'estudiante_id' => null,
            'cuota_id' => null,
            'telefono' => '+519'.$this->faker->numerify('########'),
            'contenido' => $this->faker->sentence(),
            'tipo' => TipoMensajeWhatsappEnum::CAMPANIA,
            'estado' => EstadoMensajeWhatsappEnum::PENDIENTE,
            'external_id' => null,
            'error' => null,
            'enviado_en' => null,
        ];
    }
}
