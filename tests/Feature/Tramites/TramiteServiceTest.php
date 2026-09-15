<?php

namespace Tests\Feature\Tramites;

use App\Models\User;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Enums\EstadoTramiteEnum;
use App\Modules\Tramites\Services\TramiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TramiteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TramiteService
    {
        return $this->app->make(TramiteService::class);
    }

    public function test_registrar_crea_el_tramite_en_estado_registrada(): void
    {
        $solicitante = User::factory()->create();

        $tramite = $this->service()->registrar($solicitante, CategoriaTramiteEnum::ACADEMICO, 'Constancia de no adeudo', 'La necesito para un trámite externo.');

        $this->assertSame($solicitante->id, $tramite->solicitante_id);
        $this->assertSame(EstadoTramiteEnum::REGISTRADA, $tramite->estado);
        $this->assertNull($tramite->responsable_id);
        $this->assertNull($tramite->atendido_en);
    }

    public function test_registrar_adjunta_los_archivos_subidos(): void
    {
        Storage::fake('public');

        $solicitante = User::factory()->create();
        $archivo = UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf');

        $tramite = $this->service()->registrar($solicitante, CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción', [$archivo]);

        $this->assertCount(1, $tramite->getMedia('adjuntos'));
    }

    public function test_pasar_a_un_estado_que_exige_resolucion_sin_texto_lanza_excepcion(): void
    {
        $tramite = $this->service()->registrar(User::factory()->create(), CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');
        $responsable = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->actualizarEstado($tramite, EstadoTramiteEnum::DENEGADA, $responsable, null);
    }

    public function test_pasar_a_en_revision_no_exige_resolucion(): void
    {
        $tramite = $this->service()->registrar(User::factory()->create(), CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');
        $responsable = User::factory()->create();

        $actualizado = $this->service()->actualizarEstado($tramite, EstadoTramiteEnum::EN_REVISION, $responsable, null);

        $this->assertSame(EstadoTramiteEnum::EN_REVISION, $actualizado->estado);
        $this->assertSame($responsable->id, $actualizado->responsable_id);
        $this->assertNull($actualizado->atendido_en);
    }

    public function test_marcar_atendida_registra_fecha_y_notifica_al_solicitante(): void
    {
        $solicitante = User::factory()->create();
        $responsable = User::factory()->create();
        $tramite = $this->service()->registrar($solicitante, CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');

        $actualizado = $this->service()->actualizarEstado($tramite, EstadoTramiteEnum::ATENDIDA, $responsable, 'Se entregó en mesa de partes.');

        $this->assertSame(EstadoTramiteEnum::ATENDIDA, $actualizado->estado);
        $this->assertNotNull($actualizado->atendido_en);
        $this->assertSame('Se entregó en mesa de partes.', $actualizado->resolucion);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $solicitante->id,
            'tipo' => TipoNotificacionEnum::TRAMITE_ATENDIDO->value,
        ]);
    }

    public function test_pasar_a_en_revision_no_notifica(): void
    {
        $solicitante = User::factory()->create();
        $tramite = $this->service()->registrar($solicitante, CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');

        $this->service()->actualizarEstado($tramite, EstadoTramiteEnum::EN_REVISION, User::factory()->create(), null);

        $this->assertDatabaseCount('notificaciones', 0);
    }

    public function test_mis_tramites_solo_trae_los_del_solicitante(): void
    {
        $solicitante = User::factory()->create();
        $otro = User::factory()->create();
        $this->service()->registrar($solicitante, CategoriaTramiteEnum::OTRO, 'Mío', 'Descripción');
        $this->service()->registrar($otro, CategoriaTramiteEnum::OTRO, 'Ajeno', 'Descripción');

        $resultado = $this->service()->misTramites($solicitante);

        $this->assertCount(1, $resultado);
        $this->assertSame('Mío', $resultado->first()->asunto);
    }

    public function test_todos_filtra_por_estado_y_categoria(): void
    {
        $service = $this->service();
        $solicitante = User::factory()->create();
        $responsable = User::factory()->create();

        $academicoRegistrada = $service->registrar($solicitante, CategoriaTramiteEnum::ACADEMICO, 'Uno', 'Descripción');
        $economicoAtendida = $service->registrar($solicitante, CategoriaTramiteEnum::ECONOMICO, 'Dos', 'Descripción');
        $service->actualizarEstado($economicoAtendida, EstadoTramiteEnum::ATENDIDA, $responsable, 'Listo.');

        $this->assertCount(1, $service->todos(estado: EstadoTramiteEnum::ATENDIDA));
        $this->assertCount(1, $service->todos(categoria: CategoriaTramiteEnum::ACADEMICO));
        $this->assertCount(2, $service->todos());
        $this->assertCount(0, $service->todos(estado: EstadoTramiteEnum::ARCHIVADA));
    }
}
