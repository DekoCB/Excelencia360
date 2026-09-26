<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EditarCertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): CertificadoService
    {
        return app(CertificadoService::class);
    }

    public function test_actualizar_cambia_los_datos_y_regenera_el_pdf(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $certificado = $this->service()->emitir(
            $estudiante,
            null,
            null,
            null,
            $emisor,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
            $curso,
            '3002324002',
            16.0,
        );

        $mediaIdOriginal = $certificado->getFirstMedia('pdf')->id;

        $actualizado = $this->service()->actualizar($certificado, '9999999999', 18.5, 'Corrección de datos');

        $this->assertSame('9999999999', $actualizado->numero_registro);
        $this->assertSame('18.50', (string) $actualizado->nota);
        $this->assertSame('Corrección de datos', $actualizado->observaciones);
        $this->assertNotNull($actualizado->getFirstMedia('pdf'));
        $this->assertNotSame($mediaIdOriginal, $actualizado->getFirstMedia('pdf')->id);
        $this->assertCount(1, $actualizado->getMedia('pdf'));
    }

    public function test_actualizar_puede_dejar_la_nota_y_observaciones_vacias(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $certificado = $this->service()->emitir($estudiante, null, null, 'Observación inicial', $emisor);

        $actualizado = $this->service()->actualizar($certificado, null, null, null);

        $this->assertNull($actualizado->numero_registro);
        $this->assertNull($actualizado->nota);
        $this->assertNull($actualizado->observaciones);
    }

    public function test_coordinador_puede_editar_un_certificado_desde_el_panel(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $curso = CursoCapacitacion::factory()->create();
        $certificado = $this->service()->emitir(
            $estudiante, null, null, null, $coordinador,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION, $curso, '1000000001',
        );

        $this->actingAs($coordinador);
        Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('iniciarEdicionCertificado', $certificado->id)
            ->set('editNumeroRegistro', '2000000002')
            ->set('editNota', '19')
            ->set('editObservaciones', 'Nota corregida tras revisión')
            ->call('guardarEdicionCertificado')
            ->assertHasNoErrors();

        $certificado->refresh();
        $this->assertSame('2000000002', $certificado->numero_registro);
        $this->assertSame('19.00', (string) $certificado->nota);
    }

    public function test_editar_con_un_numero_de_registro_ya_usado_por_otro_falla_la_validacion(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $this->service()->emitir(
            $estudiante, null, null, null, $coordinador,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION, $curso, 'YA-EXISTE',
        );
        $certificado = $this->service()->emitir(
            $estudiante, null, null, null, $coordinador,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION, $curso, 'ORIGINAL',
        );

        $this->actingAs($coordinador);
        Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('iniciarEdicionCertificado', $certificado->id)
            ->set('editNumeroRegistro', 'YA-EXISTE')
            ->call('guardarEdicionCertificado')
            ->assertHasErrors('editNumeroRegistro');

        $this->assertSame('ORIGINAL', $certificado->fresh()->numero_registro);
    }

    public function test_un_docente_con_solo_permiso_de_ver_no_puede_editar(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('certificados.ver');
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $certificado = $this->service()->emitir($estudiante, null, null, null, $emisor);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('iniciarEdicionCertificado', $certificado->id), report: false);

        rescue(fn () => Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('guardarEdicionCertificado'), report: false);

        $this->assertNull($certificado->fresh()->observaciones);
    }

    public function test_ver_detalle_deja_el_certificado_disponible_para_la_vista(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $certificado = $this->service()->emitir($estudiante, null, null, null, $coordinador);

        $this->actingAs($coordinador);
        Volt::test('certificados.index')
            ->set('tab', 'historial')
            ->call('verDetalle', $certificado->id)
            ->assertSet('certificadoDetalleId', $certificado->id)
            ->assertSee($certificado->codigo_verificacion);
    }
}
