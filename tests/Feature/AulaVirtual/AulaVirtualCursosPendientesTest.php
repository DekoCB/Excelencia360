<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualCursosPendientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_curso_academico_sin_horario_se_ve_como_pendiente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();

        // Un curso ya con horario, para que el semestre sea alcanzable en
        // el drill-down (Programa -> Semestre).
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id]);
        CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        // El curso pendiente: pertenece al mismo semestre, pero nunca se
        // le creó un Horario.
        $cursoPendiente = Curso::factory()->hasAttached($grado)->create(['nombre' => 'Contabilidad II']);

        $this->actingAs($coordinador);

        Volt::test('aula-virtual.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarPrograma', $grado->programa_estudio_id)
            ->call('seleccionarGrado', $grado->id)
            ->assertSee('Contabilidad II')
            ->assertSee('Todavía no tiene horario asignado');
    }

    public function test_un_docente_no_ve_cursos_pendientes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $grado->id, 'docente_id' => $docente->id]);
        CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Curso::factory()->hasAttached($grado)->create(['nombre' => 'Contabilidad II']);

        $this->actingAs($docente);

        Volt::test('aula-virtual.index')
            ->call('elegirModalidad', 'instituto')
            ->call('seleccionarPrograma', $grado->programa_estudio_id)
            ->call('seleccionarGrado', $grado->id)
            ->assertDontSee('Todavía no tiene horario asignado');
    }
}
