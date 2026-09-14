<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\MetodoEntregaEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CertificadosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_la_gestion_de_certificados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('certificados.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_gestion_de_certificados(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('certificados.index'))
            ->assertForbidden();
    }

    public function test_un_estudiante_puede_ver_y_solicitar_sus_certificados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('certificados.mis-certificados'))
            ->assertOk();
    }

    public function test_un_estudiante_no_puede_ver_la_gestion_de_certificados_del_staff(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('certificados.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_editar_la_plantilla_y_un_docente_no_ve_esa_pestana(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);
        Volt::test('certificados.index')
            ->assertSee('Plantilla')
            ->set('tab', 'plantilla')
            ->set('plantillaInstitucion', 'CEBA Actualizado')
            ->set('plantillaTitulo', 'Certificado Actualizado')
            ->set('plantillaCuerpo', 'Cuerpo actualizado para {{estudiante}}.')
            ->set('plantillaColorAcento', '#123456')
            ->call('guardarPlantilla')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plantilla_certificados', ['institucion' => 'CEBA Actualizado']);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('certificados.ver');

        $this->actingAs($docente);
        Volt::test('certificados.index')->assertDontSee('Plantilla');
    }

    public function test_la_verificacion_de_certificados_es_publica(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->get(route('certificados.verificar'))
            ->assertOk();

        Volt::test('certificados.verificar')
            ->set('codigo', $certificado->codigo_verificacion)
            ->call('verificar')
            ->assertSee($estudiante->nombreCompleto());
    }

    public function test_el_estudiante_puede_adjuntar_requisitos_al_solicitar(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->set('motivo', 'Trámite laboral')
            ->set('requisitos', [UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf')])
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasNoErrors();

        $solicitud = SolicitudCertificado::query()->where('estudiante_id', $estudiante->id)->latest()->firstOrFail();
        $this->assertCount(1, $solicitud->getMedia('requisitos'));
    }

    public function test_coordinador_puede_marcar_un_certificado_como_entregado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $coordinador);

        $this->actingAs($coordinador);
        Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('iniciarEntrega', 'certificado', $certificado->id)
            ->call('confirmarEntrega')
            ->assertHasNoErrors();

        $this->assertNotNull($certificado->fresh()->entregado_en);
    }

    public function test_un_docente_con_solo_permiso_de_ver_no_puede_marcar_entregado(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('certificados.ver');
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('iniciarEntrega', 'certificado', $certificado->id)
            ->call('confirmarEntrega'), report: false);

        $this->assertNull($certificado->fresh()->entregado_en);
    }

    public function test_solicitar_entrega_virtual_sin_correo_falla_la_validacion(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'virtual')
            ->call('solicitar')
            ->assertHasErrors(['correoEntrega']);
    }

    public function test_solicitar_libreta_sin_matricula_falla_la_validacion(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->set('tipoDocumento', TipoDocumentoEnum::LIBRETA_NOTAS->value)
            ->set('motivo', 'Necesito mis notas')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasErrors(['matriculaId']);
    }

    public function test_coordinador_emite_una_libreta_desde_una_solicitud(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);

        $solicitud = app(CertificadoService::class)->solicitar(
            $estudiante, $matricula, 'Necesito mis notas', [], TipoDocumentoEnum::LIBRETA_NOTAS,
            MetodoEntregaEnum::FISICA,
        );

        $this->actingAs($coordinador);

        Volt::test('certificados.index')
            ->call('emitirDeSolicitud', $solicitud->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('libretas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
        $this->assertNotNull($solicitud->fresh()->libreta_id);
    }
}
