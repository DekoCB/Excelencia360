<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualSeccionesTest extends TestCase
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

    public function test_elegir_un_grupo_ofrece_secciones_a_y_b_antes_de_los_grados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $gradoA = Grado::factory()->create(['nombre' => 'Grado 1', 'orden' => 1]);
        $gradoB = Grado::factory()->create(['nombre' => 'Grado 3', 'orden' => 3]);
        $this->cursoVirtualDe($ciclo, $gradoA);
        $this->cursoVirtualDe($ciclo, $gradoB);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->assertSee('Sección A')
            ->assertSee('Sección B')
            ->assertSet('seccion', null);
    }

    public function test_la_seccion_a_solo_lista_los_grados_1_y_2(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado1 = Grado::factory()->create(['nombre' => 'Grado 1', 'orden' => 1]);
        $grado2 = Grado::factory()->create(['nombre' => 'Grado 2', 'orden' => 2]);
        $grado3 = Grado::factory()->create(['nombre' => 'Grado 3', 'orden' => 3]);
        $this->cursoVirtualDe($ciclo, $grado1);
        $this->cursoVirtualDe($ciclo, $grado2);
        $this->cursoVirtualDe($ciclo, $grado3);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->assertSee('Grado 1')
            ->assertSee('Grado 2')
            ->assertDontSee('Grado 3');
    }

    public function test_la_seccion_b_solo_lista_los_grados_3_y_4(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado1 = Grado::factory()->create(['nombre' => 'Grado 1', 'orden' => 1]);
        $grado3 = Grado::factory()->create(['nombre' => 'Grado 3', 'orden' => 3]);
        $grado4 = Grado::factory()->create(['nombre' => 'Grado 4', 'orden' => 4]);
        $this->cursoVirtualDe($ciclo, $grado1);
        $this->cursoVirtualDe($ciclo, $grado3);
        $this->cursoVirtualDe($ciclo, $grado4);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'B')
            ->assertSee('Grado 3')
            ->assertSee('Grado 4')
            ->assertDontSee('Grado 1');
    }

    public function test_elegir_grado_dentro_de_una_seccion_muestra_su_curso_virtual(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create(['orden' => 1]);
        $curso = $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->call('seleccionarGrado', $grado->id)
            ->assertSee($curso->horario->curso->nombre);
    }

    public function test_volver_a_secciones_limpia_el_grado_elegido(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create(['orden' => 1]);
        $this->cursoVirtualDe($ciclo, $grado);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->call('seleccionarGrado', $grado->id)
            ->call('volverASecciones')
            ->assertSet('seccion', null)
            ->assertSet('gradoId', null)
            ->assertSee('Sección A');
    }
}
