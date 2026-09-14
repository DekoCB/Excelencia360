<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\CampaniaWhatsapp;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Notificaciones\Models\Notificacion;
use App\Modules\Notificaciones\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnviarMensajeWhatsappJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_job_notifica_al_estudiante_y_marca_el_mensaje_como_enviado(): void
    {
        $estudiante = Estudiante::factory()->create(['user_id' => User::factory()]);
        $mensaje = MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id, 'contenido' => 'Tu cuota vence pronto']);

        (new EnviarMensajeWhatsappJob($mensaje))->handle(app(NotificacionService::class));

        $mensaje->refresh();
        $this->assertSame(EstadoMensajeWhatsappEnum::ENVIADO, $mensaje->estado);
        $this->assertNotNull($mensaje->enviado_en);
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $estudiante->user_id,
            'tipo' => 'mensaje',
            'titulo' => 'Tu cuota vence pronto',
        ]);
    }

    public function test_el_job_marca_el_mensaje_como_fallido_si_el_estudiante_no_tiene_usuario_vinculado(): void
    {
        $estudiante = Estudiante::factory()->create(['user_id' => null]);
        $mensaje = MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id]);

        (new EnviarMensajeWhatsappJob($mensaje))->handle(app(NotificacionService::class));

        $mensaje->refresh();
        $this->assertSame(EstadoMensajeWhatsappEnum::FALLIDO, $mensaje->estado);
        $this->assertNotNull($mensaje->error);
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_el_job_actualiza_los_contadores_de_la_campania_y_la_completa(): void
    {
        $estudiante = Estudiante::factory()->create(['user_id' => User::factory()]);
        $campania = CampaniaWhatsapp::factory()->create([
            'total_destinatarios' => 1,
            'estado' => EstadoCampaniaEnum::ENVIANDO,
        ]);
        $mensaje = MensajeWhatsapp::factory()->create(['campania_id' => $campania->id, 'estudiante_id' => $estudiante->id]);

        (new EnviarMensajeWhatsappJob($mensaje))->handle(app(NotificacionService::class));

        $campania->refresh();
        $this->assertSame(1, $campania->enviados);
        $this->assertSame(EstadoCampaniaEnum::COMPLETADA, $campania->estado);
    }
}
