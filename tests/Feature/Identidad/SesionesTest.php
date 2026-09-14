<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SesionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_revocar_una_sesion_que_no_es_la_actual(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario);

        $sesionActualId = session()->getId();

        DB::table('sessions')->insert([
            [
                'id' => $sesionActualId,
                'user_id' => $usuario->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PestBrowser',
                'payload' => base64_encode('actual'),
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'otra-sesion-id',
                'user_id' => $usuario->id,
                'ip_address' => '10.0.0.5',
                'user_agent' => 'OtroDispositivo',
                'payload' => base64_encode('otra'),
                'last_activity' => now()->timestamp,
            ],
        ]);

        Volt::test('profile.active-sessions-form')
            ->call('revocar', 'otra-sesion-id');

        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-id']);
        $this->assertDatabaseHas('sessions', ['id' => $sesionActualId]);
    }
}
