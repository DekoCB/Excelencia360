<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Un Instituto se divide en Periodo académico -> Programa de Estudio ->
 * Semestre -> Curso (mismo drill-down que Aula Virtual), no en "secciones"
 * (A/B) -- ese paso se quitó.
 */
class AsistenciaSeccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_elegir_un_grupo_lista_los_programas_de_estudio(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $programa = ProgramaEstudio::factory()->create(['nombre' => 'Computación e Informática']);
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarGrupo', $ciclo->id)
            ->assertDontSee('Sección')
            ->assertSee('Computación e Informática');
    }

    public function test_elegir_programa_lista_los_semestres_directamente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado1 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 1', 'orden' => 1]);
        $grado3 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 3', 'orden' => 3]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado1->id]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado3->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarPrograma', $programa->id)
            ->assertDontSee('Sección')
            ->assertSee('Semestre 1')
            ->assertSee('Semestre 3');
    }

    public function test_elegir_grado_muestra_su_horario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarPrograma', $programa->id)
            ->call('seleccionarGrado', $grado->id)
            ->assertSee($horario->curso->nombre);
    }

    public function test_volver_a_grupos_limpia_programa_y_grado_elegidos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarPrograma', $programa->id)
            ->call('seleccionarGrado', $grado->id)
            ->call('volverAGrupos')
            ->assertSet('cicloId', null)
            ->assertSet('programaEstudioId', null)
            ->assertSet('gradoId', null);
    }
}
