<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * El docente no tiene "evaluaciones.ver" (solo Dirección/Coordinación la
 * tienen) -- por eso evaluaciones/libreta.blade.php necesita reconocer
 * aparte al docente dueño de alguno de los horarios del estudiante en ese
 * ciclo, para que pueda entrar a editar la nota final de su curso.
 */
class LibretaDocentePermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_docente_dueno_de_un_horario_puede_abrir_la_libreta_y_editar_la_nota(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $ciclo = Ciclo::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'docente_id' => $docente->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $ciclo])
            ->assertOk()
            ->assertDontSee('Generar libreta')
            ->set("notasEditadas.{$horario->id}", '17.00')
            ->call('guardarNotasFinales');

        $this->assertDatabaseHas('notas_finales_curso', [
            'estudiante_id' => $estudiante->id,
            'horario_id' => $horario->id,
            'nota' => '17.00',
        ]);
    }

    public function test_un_docente_ajeno_al_estudiante_no_puede_abrir_su_libreta(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $ciclo = Ciclo::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $ciclo])
            ->assertForbidden();
    }

    public function test_coordinador_ve_el_boton_de_generar_libreta(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->actingAs($coordinador);

        Volt::test('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $ciclo])
            ->assertOk()
            ->assertSee('Generar libreta');
    }
}
