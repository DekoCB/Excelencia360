<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\MetodoEntregaEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConstanciasPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_la_gestion_de_constancias(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('constancias.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_gestion_de_constancias(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('constancias.index'))
            ->assertForbidden();
    }

    public function test_un_estudiante_puede_ver_y_solicitar_sus_constancias(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('constancias.mis-constancias'))
            ->assertOk();
    }

    public function test_un_estudiante_no_puede_ver_la_gestion_de_constancias_del_staff(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('constancias.index'))
            ->assertForbidden();
    }

    public function test_el_estudiante_puede_solicitar_una_constancia_con_entrega_virtual(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('constancias.mis-constancias')
            ->set('tipoDocumento', TipoDocumentoEnum::CONSTANCIA_MATRICULA->value)
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'virtual')
            ->set('correoEntrega', 'estudiante@example.com')
            ->call('solicitar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_certificado', [
            'tipo' => TipoDocumentoEnum::CONSTANCIA_MATRICULA->value,
            'metodo_entrega' => 'virtual',
            'correo_entrega' => 'estudiante@example.com',
        ]);
    }

    public function test_no_se_puede_solicitar_un_certificado_de_estudios_desde_constancias(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('constancias.mis-constancias')
            ->set('tipoDocumento', TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value)
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasErrors(['tipoDocumento']);
    }

    /**
     * Retirada del módulo Constancias (ver TipoDocumentoEnum::constancias()):
     * ya no debe poder solicitarse ni emitirse, aunque el valor del enum
     * siga existiendo para no romper registros antiguos.
     */
    public function test_no_se_puede_solicitar_una_constancia_de_vacante(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('constancias.mis-constancias')
            ->set('tipoDocumento', TipoDocumentoEnum::CONSTANCIA_VACANTE->value)
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasErrors(['tipoDocumento']);
    }

    public function test_no_se_puede_emitir_una_constancia_de_vacante_desde_el_panel(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->set('tab', 'emitir')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CONSTANCIA_VACANTE->value)
            ->call('emitir')
            ->assertHasErrors(['tipoDocumentoEmitir']);
    }

    /**
     * Cubre de punta a punta el pipeline nuevo (enum -> valores por
     * defecto de PlantillaCertificado -> render del PDF con el membrete):
     * si falta un caso en el match de PlantillaCertificado::valoresPorDefecto()
     * para un tipo nuevo, esto falla con UnhandledMatchError.
     */
    public function test_coordinador_puede_emitir_una_constancia_de_matricula_directamente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->set('tab', 'emitir')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CONSTANCIA_MATRICULA->value)
            ->call('emitir')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => TipoDocumentoEnum::CONSTANCIA_MATRICULA->value,
        ]);
    }

    public function test_coordinador_puede_emitir_una_constancia_de_egresado_directamente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->set('tab', 'emitir')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CONSTANCIA_EGRESADO->value)
            ->call('emitir')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => TipoDocumentoEnum::CONSTANCIA_EGRESADO->value,
        ]);
    }

    public function test_coordinador_emite_una_constancia_desde_una_solicitud(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $solicitud = app(CertificadoService::class)->solicitar(
            $estudiante, null, 'Constancia de buena conducta', [], TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA,
            MetodoEntregaEnum::FISICA,
        );

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->call('emitirDeSolicitud', $solicitud->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA->value,
        ]);
    }

    public function test_coordinador_puede_marcar_una_constancia_como_entregada(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $constancia = app(CertificadoService::class)->emitir(
            $estudiante, null, null, null, $coordinador, TipoDocumentoEnum::CONSTANCIA_ESTUDIOS,
        );

        $this->actingAs($coordinador);
        Volt::test('constancias.index')
            ->set('tab', 'historial')
            ->call('iniciarEntrega', $constancia->id)
            ->call('confirmarEntrega')
            ->assertHasNoErrors();

        $this->assertNotNull($constancia->fresh()->entregado_en);
    }

    public function test_certificados_no_aparece_en_el_historial_de_constancias(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        app(CertificadoService::class)->emitir($estudiante, null, null, null, $coordinador, TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->set('tab', 'historial')
            ->assertDontSee(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->label());
    }
}
