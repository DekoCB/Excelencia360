<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Shared\Enums\EstadoUsuarioEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarCuentaActivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_desactivar_a_un_usuario_corta_el_acceso_de_su_sesion_ya_abierta(): void
    {
        config(['session.driver' => 'database']);

        $usuario = User::factory()->create(['estado' => EstadoUsuarioEnum::ACTIVO]);

        $this->actingAs($usuario)->get('/dashboard')->assertOk();

        $usuario->update(['estado' => EstadoUsuarioEnum::INACTIVO]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_una_cuenta_activa_no_se_ve_afectada(): void
    {
        config(['session.driver' => 'database']);

        $usuario = User::factory()->create(['estado' => EstadoUsuarioEnum::ACTIVO]);

        $this->actingAs($usuario)
            ->get('/dashboard')
            ->assertOk();

        $this->get('/dashboard')->assertOk();
    }
}
