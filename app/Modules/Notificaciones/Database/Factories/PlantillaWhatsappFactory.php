<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Database\Factories;

use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaWhatsapp>
 */
class PlantillaWhatsappFactory extends Factory
{
    protected $model = PlantillaWhatsapp::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Plantilla '.$this->faker->words(2, true),
            'contenido' => 'Hola {{nombre}}, este es un mensaje de prueba.',
            'activa' => true,
            'creado_por' => null,
        ];
    }
}
