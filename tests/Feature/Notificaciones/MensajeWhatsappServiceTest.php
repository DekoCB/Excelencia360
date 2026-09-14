<?php

namespace Tests\Feature\Notificaciones;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\CampaniaWhatsapp;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Notificaciones\Services\MensajeWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensajeWhatsappServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listar_sin_filtros_devuelve_todos_los_mensajes(): void
    {
        MensajeWhatsapp::factory()->count(3)->create();

        $resultado = app(MensajeWhatsappService::class)->listar([]);

        $this->assertSame(3, $resultado->total());
    }

    public function test_listar_filtra_por_tipo(): void
    {
        MensajeWhatsapp::factory()->create(['tipo' => TipoMensajeWhatsappEnum::CAMPANIA]);
        MensajeWhatsapp::factory()->create(['tipo' => TipoMensajeWhatsappEnum::RECORDATORIO]);
        MensajeWhatsapp::factory()->create(['tipo' => TipoMensajeWhatsappEnum::ENTRANTE]);

        $resultado = app(MensajeWhatsappService::class)->listar(['tipo' => TipoMensajeWhatsappEnum::RECORDATORIO->value]);

        $this->assertSame(1, $resultado->total());
        $this->assertSame(TipoMensajeWhatsappEnum::RECORDATORIO, $resultado->items()[0]->tipo);
    }

    public function test_listar_filtra_por_estado(): void
    {
        MensajeWhatsapp::factory()->create(['estado' => EstadoMensajeWhatsappEnum::FALLIDO]);
        MensajeWhatsapp::factory()->create(['estado' => EstadoMensajeWhatsappEnum::ENTREGADO]);

        $resultado = app(MensajeWhatsappService::class)->listar(['estado' => EstadoMensajeWhatsappEnum::FALLIDO->value]);

        $this->assertSame(1, $resultado->total());
        $this->assertSame(EstadoMensajeWhatsappEnum::FALLIDO, $resultado->items()[0]->estado);
    }

    public function test_listar_filtra_por_campania(): void
    {
        $campania = CampaniaWhatsapp::factory()->create();
        MensajeWhatsapp::factory()->create(['campania_id' => $campania->id]);
        MensajeWhatsapp::factory()->create();

        $resultado = app(MensajeWhatsappService::class)->listar(['campania_id' => $campania->id]);

        $this->assertSame(1, $resultado->total());
    }

    public function test_mis_mensajes_solo_incluye_los_del_estudiante_indicado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $otroEstudiante = Estudiante::factory()->create();
        MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id]);
        MensajeWhatsapp::factory()->create(['estudiante_id' => $otroEstudiante->id]);

        $resultado = app(MensajeWhatsappService::class)->misMensajes($estudiante);

        $this->assertSame(1, $resultado->total());
    }

    public function test_mis_mensajes_excluye_mensajes_entrantes_e_incidencias(): void
    {
        $estudiante = Estudiante::factory()->create();
        MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoMensajeWhatsappEnum::CAMPANIA]);
        MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoMensajeWhatsappEnum::ENTRANTE]);
        MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoMensajeWhatsappEnum::INCIDENCIA]);

        $resultado = app(MensajeWhatsappService::class)->misMensajes($estudiante);

        $this->assertSame(1, $resultado->total());
        $this->assertSame(TipoMensajeWhatsappEnum::CAMPANIA, $resultado->items()[0]->tipo);
    }
}
