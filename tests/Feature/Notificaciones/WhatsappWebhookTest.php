<?php

namespace Tests\Feature\Notificaciones;

use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.verify_token' => 'token-de-prueba']);
    }

    public function test_la_verificacion_responde_el_challenge_con_el_token_correcto(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=token-de-prueba&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_la_verificacion_rechaza_un_token_incorrecto(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=token-equivocado&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_una_actualizacion_de_estado_marca_el_mensaje_como_entregado(): void
    {
        $mensaje = MensajeWhatsapp::factory()->create([
            'external_id' => 'wamid.ABC123',
            'estado' => EstadoMensajeWhatsappEnum::ENVIADO,
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.ABC123',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame(EstadoMensajeWhatsappEnum::ENTREGADO, $mensaje->refresh()->estado);
    }

    public function test_un_mensaje_entrante_se_registra_como_recibido(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '51987654321',
                            'id' => 'wamid.XYZ789',
                            'text' => ['body' => 'Hola, tengo una consulta'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertDatabaseHas('mensajes_whatsapp', [
            'telefono' => '+51987654321',
            'contenido' => 'Hola, tengo una consulta',
            'tipo' => TipoMensajeWhatsappEnum::ENTRANTE->value,
            'estado' => EstadoMensajeWhatsappEnum::RECIBIDO->value,
        ]);
    }
}
