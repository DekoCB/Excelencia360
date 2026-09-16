<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CertificadoCapacitacionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function coordinador(): User
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        return $coordinador;
    }

    public function test_coordinador_emite_un_certificado_de_capacitacion_desde_el_panel(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('estudianteSeleccionadoNombre', $estudiante->nombreCompleto())
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoCapacitacionId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => 'certificado_capacitacion',
            'curso_capacitacion_id' => $curso->id,
            'numero_registro' => '3002324002',
        ]);
    }

    public function test_no_permite_emitir_un_certificado_de_capacitacion_sin_curso(): void
    {
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasErrors(['cursoCapacitacionId']);

        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_no_permite_emitir_un_certificado_de_capacitacion_sin_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoCapacitacionId', (string) $curso->id)
            ->call('emitir')
            ->assertHasErrors(['numeroRegistro']);

        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_no_permite_repetir_un_numero_de_registro_ya_usado(): void
    {
        $curso = CursoCapacitacion::factory()->create();
        $estudianteUno = Estudiante::factory()->create();
        $estudianteDos = Estudiante::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudianteUno->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoCapacitacionId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasNoErrors();

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudianteDos->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoCapacitacionId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasErrors(['numeroRegistro']);

        $this->assertDatabaseCount('certificados', 1);
    }

    public function test_coordinador_crea_un_curso_de_capacitacion_desde_el_panel(): void
    {
        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->call('abrirFormCurso')
            ->set('cursoNombre', 'Ofimática Nivel Avanzado')
            ->set('cursoHorasLectivas', '130')
            ->set('cursoDocumentoAutorizacion', 'R.D.R. N°2182-2023-DREP')
            ->call('guardarCurso')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos_capacitacion', [
            'nombre' => 'Ofimática Nivel Avanzado',
            'horas_lectivas' => 130,
        ]);
    }

    public function test_un_docente_no_puede_gestionar_cursos_de_capacitacion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('certificados.index')
            ->call('abrirFormCurso'), report: false);

        $this->assertDatabaseCount('cursos_capacitacion', 0);
    }

    public function test_coordinador_elimina_un_curso_de_capacitacion(): void
    {
        $curso = CursoCapacitacion::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->call('eliminarCurso', $curso->id);

        $this->assertDatabaseMissing('cursos_capacitacion', ['id' => $curso->id]);
    }
}
