<?php

namespace Tests\Feature\Incidencias;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class IncidenciasPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Queue::fake();
    }

    private function estudianteMatriculadoEn(Horario $horario): Estudiante
    {
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        return $estudiante;
    }

    public function test_un_usuario_sin_permisos_de_incidencias_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('incidencias.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_reportar_una_incidencia_de_cualquier_estudiante(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipo', 'conducta')
            ->set('descripcion', 'Conversó durante la evaluación.')
            ->set('fecha', now()->format('Y-m-d'))
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('incidencias', [
            'estudiante_id' => $estudiante->id,
            'reportado_por' => $coordinador->id,
        ]);
    }

    public function test_un_docente_puede_reportar_una_incidencia_de_su_propio_alumno(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $estudiante = $this->estudianteMatriculadoEn($horario);

        $this->actingAs($docente);

        Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipo', 'disciplina')
            ->set('descripcion', 'Falta de respeto a un compañero.')
            ->set('fecha', now()->format('Y-m-d'))
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('incidencias', [
            'estudiante_id' => $estudiante->id,
            'reportado_por' => $docente->id,
        ]);
    }

    public function test_un_docente_no_puede_reportar_una_incidencia_de_un_alumno_ajeno(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $horarioAjeno = Horario::factory()->create(['docente_id' => $otroDocente->id]);
        $estudianteAjeno = $this->estudianteMatriculadoEn($horarioAjeno);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $estudianteAjeno->id, $estudianteAjeno->nombreCompleto())
            ->set('tipo', 'conducta')
            ->set('descripcion', 'Descripción de prueba.')
            ->set('fecha', now()->format('Y-m-d'))
            ->call('crear'), report: false);

        $this->assertDatabaseMissing('incidencias', ['estudiante_id' => $estudianteAjeno->id]);
    }

    public function test_un_docente_ve_las_incidencias_de_sus_alumnos_en_el_listado(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $estudiante = $this->estudianteMatriculadoEn($horario);

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);
        Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipo', 'conducta')
            ->set('descripcion', 'Incidencia reportada por coordinación.')
            ->set('fecha', now()->format('Y-m-d'))
            ->call('crear');

        $this->actingAs($docente);

        Volt::test('incidencias.index')
            ->assertSee('Incidencia reportada por coordinación.');
    }

    public function test_un_estudiante_ve_solo_sus_propias_incidencias(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $otroEstudiante = Estudiante::factory()->create();

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);
        Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipo', 'conducta')->set('descripcion', 'Incidencia propia.')->set('fecha', now()->format('Y-m-d'))
            ->call('crear');
        Volt::test('incidencias.index')
            ->call('seleccionarEstudiante', $otroEstudiante->id, $otroEstudiante->nombreCompleto())
            ->set('tipo', 'conducta')->set('descripcion', 'Incidencia ajena.')->set('fecha', now()->format('Y-m-d'))
            ->call('crear');

        $this->actingAs($usuario);

        Volt::test('incidencias.index')
            ->assertSee('Incidencia propia.')
            ->assertDontSee('Incidencia ajena.');
    }

    public function test_un_estudiante_no_puede_reportar_incidencias(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('incidencias.index')
            ->assertDontSee('Reportar incidencia');
    }
}
