<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaSeccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_elegir_un_grupo_ofrece_secciones_a_y_b_antes_de_los_grados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $gradoA = Grado::factory()->create(['nombre' => 'Grado 1', 'orden' => 1]);
        $gradoB = Grado::factory()->create(['nombre' => 'Grado 3', 'orden' => 3]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $gradoA->id]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $gradoB->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
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
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado1->id]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado2->id]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado3->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->assertSee('Grado 1')
            ->assertSee('Grado 2')
            ->assertDontSee('Grado 3');
    }

    public function test_elegir_grado_dentro_de_una_seccion_muestra_su_horario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create(['orden' => 1]);
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->call('seleccionarGrado', $grado->id)
            ->assertSee($horario->curso->nombre);
    }

    public function test_volver_a_secciones_limpia_el_grado_elegido(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create(['orden' => 1]);
        Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia.index')
            ->call('seleccionarGrupo', $ciclo->id)
            ->call('seleccionarSeccion', 'A')
            ->call('seleccionarGrado', $grado->id)
            ->call('volverASecciones')
            ->assertSet('seccion', null)
            ->assertSet('gradoId', null)
            ->assertSee('Sección A');
    }
}
