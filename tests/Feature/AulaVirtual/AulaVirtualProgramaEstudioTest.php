<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualProgramaEstudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoVirtualDe(Ciclo $ciclo, Grado $grado): CursoVirtual
    {
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    public function test_elegir_un_programa_de_estudio_ofrece_sus_semestres_sin_pasar_por_secciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 1']);
        $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programa->id)
            ->assertSee('Semestre 1')
            ->assertDontSee('Sección A')
            ->assertDontSee('Sección B');
    }

    public function test_solo_lista_los_semestres_del_programa_elegido(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $programaA = ProgramaEstudio::factory()->create();
        $programaB = ProgramaEstudio::factory()->create();
        $gradoDeA = Grado::factory()->create(['programa_estudio_id' => $programaA->id, 'nombre' => 'Semestre de A']);
        $gradoDeB = Grado::factory()->create(['programa_estudio_id' => $programaB->id, 'nombre' => 'Semestre de B']);
        $this->cursoVirtualDe($ciclo, $gradoDeA);
        $this->cursoVirtualDe($ciclo, $gradoDeB);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programaA->id)
            ->assertSee('Semestre de A')
            ->assertDontSee('Semestre de B');
    }

    public function test_elegir_semestre_dentro_de_un_programa_muestra_su_curso_virtual(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $curso = $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programa->id)
            ->call('seleccionarGrado', $grado->id)
            ->assertSee($curso->horario->curso->nombre);
    }

    public function test_volver_a_semestres_limpia_el_grado_elegido(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre único']);
        $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programa->id)
            ->call('seleccionarGrado', $grado->id)
            ->call('volverASemestres')
            ->assertSet('gradoId', null)
            ->assertSee('Semestre único');
    }

    public function test_volver_a_programas_limpia_programa_y_grado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $programa = ProgramaEstudio::factory()->create(['nombre' => 'Programa único']);
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programa->id)
            ->call('seleccionarGrado', $grado->id)
            ->call('volverAProgramas')
            ->assertSet('programaEstudioId', null)
            ->assertSet('gradoId', null)
            ->assertSee('Programa único');
    }

    public function test_un_curso_de_un_periodo_distinto_al_actual_no_aparece(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $cicloActivo = Ciclo::factory()->activo()->create();
        $cicloPasado = Ciclo::factory()->create(['estado' => 'cerrado']);
        $programa = ProgramaEstudio::factory()->create();
        $gradoActual = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre vigente']);
        $gradoPasado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre pasado']);
        $this->cursoVirtualDe($cicloActivo, $gradoActual);
        $this->cursoVirtualDe($cicloPasado, $gradoPasado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarPrograma', $programa->id)
            ->assertSee('Semestre vigente')
            ->assertDontSee('Semestre pasado');
    }
}
