<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Enums\TipoCertificadoSubidoEnum;
use App\Modules\Matricula\Models\CertificadoSubido;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\CertificadoSubidoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CertificadoSubidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_servicio_sube_un_certificado_de_estudios_sin_curso(): void
    {
        Storage::fake('public');

        $estudiante = Estudiante::factory()->create();

        $certificado = app(CertificadoSubidoService::class)->subir(
            $estudiante,
            TipoCertificadoSubidoEnum::ESTUDIOS,
            UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'),
            null,
            null,
        );

        $this->assertSame(TipoCertificadoSubidoEnum::ESTUDIOS, $certificado->tipo);
        $this->assertNull($certificado->curso_id);
        $this->assertNotNull($certificado->getFirstMedia('archivo'));
    }

    public function test_el_servicio_exige_curso_para_un_certificado_de_capacitacion(): void
    {
        Storage::fake('public');

        $estudiante = Estudiante::factory()->create();

        $this->expectException(ValidationException::class);

        app(CertificadoSubidoService::class)->subir(
            $estudiante,
            TipoCertificadoSubidoEnum::CAPACITACION,
            UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'),
            null,
            null,
        );
    }

    public function test_un_estudiante_puede_tener_varios_certificados_de_distintos_cursos(): void
    {
        Storage::fake('public');

        $estudiante = Estudiante::factory()->create();
        $cursoUno = Curso::factory()->capacitacion()->create();
        $cursoDos = Curso::factory()->capacitacion()->create();

        $service = app(CertificadoSubidoService::class);
        $service->subir($estudiante, TipoCertificadoSubidoEnum::CAPACITACION, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), $cursoUno->id, null);
        $service->subir($estudiante, TipoCertificadoSubidoEnum::CAPACITACION, UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'), $cursoDos->id, null);

        $this->assertSame(2, $estudiante->certificadosSubidos()->count());
    }

    public function test_agregar_un_certificado_de_estudios_desde_la_ficha(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('mostrarFormCertificado')
            ->set('certificadoTipoNuevo', 'estudios')
            ->set('certificadoArchivoNuevo', UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'))
            ->call('guardarCertificado')
            ->assertHasNoErrors();

        $this->assertSame(1, $estudiante->certificadosSubidos()->count());
    }

    public function test_agregar_un_certificado_de_capacitacion_exige_elegir_el_curso(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('mostrarFormCertificado')
            ->set('certificadoTipoNuevo', 'capacitacion')
            ->set('certificadoArchivoNuevo', UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'))
            ->call('guardarCertificado')
            ->assertHasErrors(['certificadoCursoNuevoId']);
    }

    public function test_agregar_certificado_de_capacitacion_desde_el_modal_de_ficha(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Excel Avanzado']);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('mostrarFormCertificado')
            ->set('certificadoTipoNuevo', 'capacitacion')
            ->set('certificadoCursoNuevoId', (string) $curso->id)
            ->set('certificadoArchivoNuevo', UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'))
            ->call('guardarCertificado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados_subidos', [
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
        ]);
    }

    public function test_eliminar_un_certificado_subido_desde_la_ficha(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $certificado = CertificadoSubido::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarCertificado', $certificado->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('certificados_subidos', ['id' => $certificado->id]);
    }

    public function test_el_estudiante_ve_sus_certificados_oficiales_subidos_en_mis_certificados(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Diseño Gráfico']);
        app(CertificadoSubidoService::class)->subir(
            $estudiante,
            TipoCertificadoSubidoEnum::CAPACITACION,
            UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'),
            $curso->id,
            null,
        );

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->assertSee('Mis certificados oficiales')
            ->assertSee('Diseño Gráfico');
    }

    public function test_un_docente_no_puede_agregar_un_certificado_subido(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('matricula.ver');
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($docente);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('mostrarFormCertificado')
            ->assertForbidden();
    }
}
