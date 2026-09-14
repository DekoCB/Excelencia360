<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use App\Modules\Notificaciones\Services\CampaniaWhatsappService;
use App\Modules\Pagos\Models\BloqueoAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CampaniaWhatsappServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_destinatarios_filtra_por_grado(): void
    {
        $grado = Grado::factory()->create();
        $estudianteEnGrado = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteEnGrado->id, 'grado_id' => $grado->id]);

        $otroEstudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $otroEstudiante->id]);

        $destinatarios = app(CampaniaWhatsappService::class)->resolverDestinatarios(['grado_id' => $grado->id]);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($estudianteEnGrado->id, $destinatarios->first()->id);
    }

    public function test_resolver_destinatarios_filtra_solo_con_deuda(): void
    {
        $estudianteBloqueado = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteBloqueado->id]);
        BloqueoAcceso::factory()->create(['estudiante_id' => $estudianteBloqueado->id, 'activo' => true]);

        $estudianteAlDia = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteAlDia->id]);

        $destinatarios = app(CampaniaWhatsappService::class)->resolverDestinatarios(['solo_con_deuda' => true]);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($estudianteBloqueado->id, $destinatarios->first()->id);
    }

    public function test_crear_y_enviar_encola_un_mensaje_por_destinatario(): void
    {
        Queue::fake();

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plantilla = PlantillaWhatsapp::factory()->create();
        $creador = User::factory()->create();

        $campania = app(CampaniaWhatsappService::class)->crearYEnviar('Campaña de prueba', $plantilla, [], $creador);

        $this->assertSame(1, $campania->total_destinatarios);
        $this->assertSame(EstadoCampaniaEnum::ENVIANDO, $campania->estado);
        Queue::assertPushed(EnviarMensajeWhatsappJob::class, 1);
    }

    public function test_crear_y_enviar_falla_si_el_segmento_no_tiene_destinatarios(): void
    {
        Queue::fake();

        $plantilla = PlantillaWhatsapp::factory()->create();
        $creador = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CampaniaWhatsappService::class)->crearYEnviar('Campaña vacía', $plantilla, ['grado_id' => 999999], $creador);
    }
}
