<?php

namespace Tests\Feature\Notificaciones;

use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantillaWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderizar_sustituye_los_placeholders_provistos(): void
    {
        $plantilla = PlantillaWhatsapp::factory()->create([
            'contenido' => 'Hola {{nombre}}, tu cuota vence el {{fecha}}.',
        ]);

        $resultado = $plantilla->renderizar(['nombre' => 'Ana Torres', 'fecha' => '15/08/2026']);

        $this->assertSame('Hola Ana Torres, tu cuota vence el 15/08/2026.', $resultado);
    }

    public function test_renderizar_deja_intactos_los_placeholders_sin_valor(): void
    {
        $plantilla = PlantillaWhatsapp::factory()->create([
            'contenido' => 'Hola {{nombre}}, saldo: {{monto}}.',
        ]);

        $resultado = $plantilla->renderizar(['nombre' => 'Ana']);

        $this->assertSame('Hola Ana, saldo: {{monto}}.', $resultado);
    }
}
