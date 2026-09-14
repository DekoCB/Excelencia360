<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Models\Notificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CampanaNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_campana_muestra_las_notificaciones_no_leidas(): void
    {
        $usuario = User::factory()->create();
        Notificacion::factory()->for($usuario)->create(['titulo' => 'Tu tarea fue calificada']);

        $this->actingAs($usuario);

        Volt::test('layout.navigation')
            ->assertSee('Tu tarea fue calificada')
            ->assertSee('Marcar todas como leídas');
    }

    public function test_sin_notificaciones_no_leidas_no_se_muestra_marcar_todas(): void
    {
        $usuario = User::factory()->create();
        Notificacion::factory()->for($usuario)->leida()->create();

        $this->actingAs($usuario);

        Volt::test('layout.navigation')
            ->assertDontSee('Marcar todas como leídas');
    }

    public function test_marcar_leida_registra_la_fecha_de_lectura(): void
    {
        $usuario = User::factory()->create();
        $notificacion = Notificacion::factory()->for($usuario)->create();

        $this->actingAs($usuario);

        Volt::test('layout.navigation')->call('marcarLeida', $notificacion);

        $this->assertNotNull($notificacion->fresh()->leida_en);
    }

    public function test_marcar_leida_redirige_a_la_url_de_la_notificacion(): void
    {
        $usuario = User::factory()->create();
        $notificacion = Notificacion::factory()->for($usuario)->create(['url' => '/dashboard']);

        $this->actingAs($usuario);

        Volt::test('layout.navigation')
            ->call('marcarLeida', $notificacion)
            ->assertRedirect('/dashboard');
    }

    public function test_marcar_todas_leidas_deja_sin_pendientes_al_usuario(): void
    {
        $usuario = User::factory()->create();
        Notificacion::factory()->for($usuario)->count(3)->create();

        $this->actingAs($usuario);

        Volt::test('layout.navigation')->call('marcarTodasLeidas');

        $this->assertSame(0, Notificacion::query()->where('user_id', $usuario->id)->whereNull('leida_en')->count());
    }

    public function test_un_usuario_no_puede_marcar_como_leida_una_notificacion_ajena(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create();
        $notificacionAjena = Notificacion::factory()->for($otro)->create();

        $this->actingAs($usuario);

        Volt::test('layout.navigation')
            ->call('marcarLeida', $notificacionAjena)
            ->assertForbidden();
    }
}
