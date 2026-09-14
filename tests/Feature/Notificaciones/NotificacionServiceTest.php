<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Models\Notificacion;
use App\Modules\Notificaciones\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): NotificacionService
    {
        return $this->app->make(NotificacionService::class);
    }

    public function test_notificar_crea_una_notificacion_no_leida(): void
    {
        $usuario = User::factory()->create();

        $notificacion = $this->service()->notificar($usuario, TipoNotificacionEnum::TAREA_CALIFICADA, 'Tu tarea fue calificada', '/algun-enlace');

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $usuario->id,
            'tipo' => TipoNotificacionEnum::TAREA_CALIFICADA->value,
            'titulo' => 'Tu tarea fue calificada',
            'url' => '/algun-enlace',
        ]);
        $this->assertFalse($notificacion->estaLeida());
    }

    public function test_notificar_varios_crea_una_notificacion_por_cada_usuario(): void
    {
        $usuarios = User::factory()->count(3)->create();

        $this->service()->notificarVarios($usuarios, TipoNotificacionEnum::EVALUACION_PUBLICADA, 'Se publicó una evaluación');

        $this->assertSame(3, Notificacion::query()->count());
    }

    public function test_recientes_devuelve_las_mas_nuevas_primero(): void
    {
        $usuario = User::factory()->create();
        $antigua = Notificacion::factory()->for($usuario)->create(['created_at' => now()->subDay()]);
        $nueva = Notificacion::factory()->for($usuario)->create(['created_at' => now()]);

        $recientes = $this->service()->recientes($usuario);

        $this->assertSame($nueva->id, $recientes->first()->id);
        $this->assertSame($antigua->id, $recientes->last()->id);
    }

    public function test_recientes_no_incluye_notificaciones_de_otro_usuario(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create();
        Notificacion::factory()->for($otro)->create();

        $this->assertTrue($this->service()->recientes($usuario)->isEmpty());
    }

    public function test_recientes_respeta_el_limite(): void
    {
        $usuario = User::factory()->create();
        Notificacion::factory()->for($usuario)->count(5)->create();

        $this->assertCount(3, $this->service()->recientes($usuario, 3));
    }

    public function test_contar_no_leidas_ignora_las_ya_leidas(): void
    {
        $usuario = User::factory()->create();
        Notificacion::factory()->for($usuario)->count(2)->create();
        Notificacion::factory()->for($usuario)->leida()->create();

        $this->assertSame(2, $this->service()->contarNoLeidas($usuario));
    }

    public function test_marcar_leida_le_pone_fecha_de_lectura(): void
    {
        $notificacion = Notificacion::factory()->create();

        $this->service()->marcarLeida($notificacion);

        $this->assertNotNull($notificacion->fresh()->leida_en);
    }

    public function test_marcar_leida_no_pisa_una_fecha_de_lectura_ya_registrada(): void
    {
        $notificacion = Notificacion::factory()->leida()->create(['leida_en' => now()->subDay()]);
        $fechaOriginal = $notificacion->leida_en;

        $this->service()->marcarLeida($notificacion);

        $this->assertEquals($fechaOriginal, $notificacion->fresh()->leida_en);
    }

    public function test_marcar_todas_leidas_solo_afecta_al_usuario_indicado(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create();
        Notificacion::factory()->for($usuario)->count(2)->create();
        Notificacion::factory()->for($otro)->create();

        $this->service()->marcarTodasLeidas($usuario);

        $this->assertSame(0, $this->service()->contarNoLeidas($usuario));
        $this->assertSame(1, $this->service()->contarNoLeidas($otro));
    }
}
