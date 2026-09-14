<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Models\Notificacion;
use App\Modules\Notificaciones\Services\NotificacionService;
use App\Shared\Enums\MetodoEntregaEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CertificadoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_emitir_genera_numero_correlativo_y_codigo_de_verificacion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, $matricula, null, null, $emisor);

        $this->assertNotEmpty($certificado->numero);
        $this->assertStringEndsWith('-'.now()->format('Y'), $certificado->numero);
        $this->assertSame(10, mb_strlen($certificado->codigo_verificacion));
        $this->assertFalse($certificado->es_duplicado);
        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_numeros_correlativos_no_se_repiten_dentro_del_mismo_anio(): void
    {
        $emisor = User::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $primero = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        $segundo = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->assertNotSame($primero->numero, $segundo->numero);
    }

    public function test_si_falla_a_mitad_de_la_emision_no_queda_un_certificado_huerfano(): void
    {
        $usuario = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $solicitud = SolicitudCertificado::factory()->create([
            'estudiante_id' => $estudiante->id,
            'tipo' => TipoDocumentoEnum::CERTIFICADO_ESTUDIOS,
        ]);
        $emisor = User::factory()->create();

        // La notificación es lo último que hace emitir(): si falla ahí, ya
        // se creó el Certificado y se generó el PDF -- justo el escenario
        // que debía dejar un registro huérfano antes de envolver todo en
        // una transacción.
        $this->mock(NotificacionService::class, function ($mock) {
            $mock->shouldReceive('notificar')->andThrow(new RuntimeException('fallo simulado'));
        });

        try {
            app(CertificadoService::class)->emitir($estudiante, null, $solicitud, null, $emisor);
            $this->fail('Se esperaba que emitir() propagara la excepción simulada.');
        } catch (RuntimeException $e) {
            $this->assertSame('fallo simulado', $e->getMessage());
        }

        $this->assertSame(0, Certificado::query()->count());
        $this->assertSame(EstadoSolicitudCertificadoEnum::PENDIENTE, $solicitud->fresh()->estado);
    }

    public function test_duplicar_conserva_el_mismo_codigo_de_verificacion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $original = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        $duplicado = app(CertificadoService::class)->duplicar($original, 'Extravío', $emisor);

        $this->assertTrue($duplicado->es_duplicado);
        $this->assertSame($original->codigo_verificacion, $duplicado->codigo_verificacion);
        $this->assertSame($original->id, $duplicado->certificado_original_id);
        $this->assertNotSame($original->numero, $duplicado->numero);
    }

    public function test_duplicar_no_falla_si_el_estudiante_del_original_fue_eliminado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $original = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        $estudiante->delete();

        $duplicado = app(CertificadoService::class)->duplicar($original, 'Extravío', $emisor);

        $this->assertTrue($duplicado->es_duplicado);
        $this->assertNotNull($duplicado->getFirstMedia('pdf'));
    }

    public function test_verificar_encuentra_el_certificado_original_por_codigo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $encontrado = app(CertificadoService::class)->verificar($certificado->codigo_verificacion);

        $this->assertNotNull($encontrado);
        $this->assertSame($certificado->id, $encontrado->id);
    }

    public function test_verificar_con_codigo_invalido_no_encuentra_nada(): void
    {
        $encontrado = app(CertificadoService::class)->verificar('CODIGOINEXISTENTE');

        $this->assertNull($encontrado);
    }

    public function test_emitir_a_partir_de_una_solicitud_la_marca_como_atendida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $certificado = $service->emitir($estudiante, null, $solicitud, null, $emisor);

        $this->assertSame(EstadoSolicitudCertificadoEnum::ATENDIDA, $solicitud->refresh()->estado);
        $this->assertSame($certificado->id, $solicitud->certificado_id);
    }

    public function test_rechazar_solicitud_registra_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $revisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $service->rechazarSolicitud($solicitud, 'Deuda pendiente', $revisor);

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitudCertificadoEnum::RECHAZADA, $solicitud->estado);
        $this->assertSame('Deuda pendiente', $solicitud->motivo_rechazo);
    }

    public function test_no_permite_rechazar_una_solicitud_ya_atendida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $service->emitir($estudiante, null, $solicitud, null, $emisor);

        $this->expectException(ValidationException::class);
        $service->rechazarSolicitud($solicitud, 'Ya no aplica', $emisor);
    }

    public function test_solicitar_adjunta_los_requisitos_subidos(): void
    {
        $estudiante = Estudiante::factory()->create();

        $solicitud = app(CertificadoService::class)->solicitar($estudiante, null, 'Trámite laboral', [
            UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('partida.jpg'),
        ]);

        $this->assertCount(2, $solicitud->getMedia('requisitos'));
    }

    public function test_emitir_notifica_al_estudiante_que_su_certificado_esta_listo(): void
    {
        $usuario = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $emisor = User::factory()->create();

        app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $usuario->id,
            'tipo' => TipoNotificacionEnum::CERTIFICADO_LISTO->value,
        ]);
    }

    public function test_emitir_no_falla_si_el_estudiante_no_tiene_cuenta_de_usuario_vinculada(): void
    {
        $estudiante = Estudiante::factory()->create(['user_id' => null]);
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->assertNotNull($certificado);
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_marcar_entregado_registra_fecha_y_quien_entrego(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $recepcionista = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        app(CertificadoService::class)->marcarEntregado($certificado, $recepcionista);

        $certificado->refresh();
        $this->assertNotNull($certificado->entregado_en);
        $this->assertSame($recepcionista->id, $certificado->entregado_por);
    }

    public function test_no_permite_marcar_entregado_dos_veces(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $certificado = $service->emitir($estudiante, null, null, null, $emisor);
        $service->marcarEntregado($certificado, $emisor);

        $this->expectException(ValidationException::class);
        $service->marcarEntregado($certificado, $emisor);
    }

    public function test_marcar_entregado_con_foto_la_adjunta_como_constancia(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $certificado = $service->emitir($estudiante, null, null, null, $emisor);
        $service->marcarEntregado($certificado, $emisor, UploadedFile::fake()->image('entrega.jpg'));

        $this->assertNotNull($certificado->getFirstMedia('foto_entrega'));
    }

    public function test_emitir_sin_tipo_explicito_usa_certificado_de_estudios_por_defecto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->assertSame(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, $certificado->tipo);
    }

    public function test_emitir_directo_con_tipo_constancia_lo_persiste(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir(
            $estudiante, null, null, null, $emisor, TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA,
        );

        $this->assertSame(TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA, $certificado->tipo);
        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_solicitar_persiste_tipo_metodo_de_entrega_y_correo(): void
    {
        $estudiante = Estudiante::factory()->create();

        $solicitud = app(CertificadoService::class)->solicitar(
            $estudiante,
            null,
            'Trámite laboral',
            [],
            TipoDocumentoEnum::CONSTANCIA_VACANTE,
            MetodoEntregaEnum::VIRTUAL,
            'estudiante@example.com',
        );

        $this->assertSame(TipoDocumentoEnum::CONSTANCIA_VACANTE, $solicitud->tipo);
        $this->assertSame(MetodoEntregaEnum::VIRTUAL, $solicitud->metodo_entrega);
        $this->assertSame('estudiante@example.com', $solicitud->correo_entrega);
    }

    public function test_solicitar_con_entrega_fisica_no_guarda_correo_aunque_se_pase_uno(): void
    {
        $estudiante = Estudiante::factory()->create();

        $solicitud = app(CertificadoService::class)->solicitar(
            $estudiante,
            null,
            'Trámite laboral',
            [],
            TipoDocumentoEnum::CERTIFICADO_ESTUDIOS,
            MetodoEntregaEnum::FISICA,
            'no-deberia-guardarse@example.com',
        );

        $this->assertNull($solicitud->correo_entrega);
    }

    public function test_emitir_desde_una_solicitud_hereda_su_tipo_y_metodo_de_entrega(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar(
            $estudiante, null, 'Trámite laboral', [], TipoDocumentoEnum::CONSTANCIA_ESTUDIOS,
            MetodoEntregaEnum::VIRTUAL, 'correo@example.com',
        );

        $certificado = $service->emitir($estudiante, null, $solicitud, null, $emisor);

        $this->assertSame(TipoDocumentoEnum::CONSTANCIA_ESTUDIOS, $certificado->tipo);
        $this->assertSame(MetodoEntregaEnum::VIRTUAL, $certificado->metodo_entrega);
        $this->assertSame('correo@example.com', $certificado->correo_entrega);
    }

    public function test_emitir_libreta_desde_solicitud_genera_la_libreta_y_cierra_la_solicitud(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar(
            $estudiante, $matricula, 'Quiero mi libreta', [], TipoDocumentoEnum::LIBRETA_NOTAS,
            MetodoEntregaEnum::FISICA,
        );

        $libreta = $service->emitirLibretaDesdeSolicitud($solicitud, $emisor);

        $this->assertNotNull($libreta->generado_en);
        $this->assertSame(EstadoSolicitudCertificadoEnum::ATENDIDA, $solicitud->refresh()->estado);
        $this->assertSame($libreta->id, $solicitud->libreta_id);
    }

    public function test_emitir_libreta_desde_solicitud_sin_matricula_lanza_excepcion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar(
            $estudiante, null, 'Quiero mi libreta', [], TipoDocumentoEnum::LIBRETA_NOTAS, MetodoEntregaEnum::FISICA,
        );

        $this->expectException(ValidationException::class);
        $service->emitirLibretaDesdeSolicitud($solicitud, $emisor);
    }

    public function test_marcar_libreta_entregada_registra_fecha_y_quien_entrego(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, $matricula, 'Libreta', [], TipoDocumentoEnum::LIBRETA_NOTAS, MetodoEntregaEnum::FISICA);
        $libreta = $service->emitirLibretaDesdeSolicitud($solicitud, $emisor);

        $service->marcarLibretaEntregada($libreta, $emisor);

        $libreta->refresh();
        $this->assertNotNull($libreta->entregado_en);
        $this->assertSame($emisor->id, $libreta->entregado_por);
    }

    public function test_no_permite_marcar_libreta_entregada_dos_veces(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, $matricula, 'Libreta', [], TipoDocumentoEnum::LIBRETA_NOTAS, MetodoEntregaEnum::FISICA);
        $libreta = $service->emitirLibretaDesdeSolicitud($solicitud, $emisor);
        $service->marcarLibretaEntregada($libreta, $emisor);

        $this->expectException(ValidationException::class);
        $service->marcarLibretaEntregada($libreta, $emisor);
    }

    public function test_duplicar_conserva_el_tipo_del_original(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $original = $service->emitir($estudiante, null, null, null, $emisor, TipoDocumentoEnum::CONSTANCIA_ESTUDIOS);
        $duplicado = $service->duplicar($original, null, $emisor);

        $this->assertSame(TipoDocumentoEnum::CONSTANCIA_ESTUDIOS, $duplicado->tipo);
    }
}
