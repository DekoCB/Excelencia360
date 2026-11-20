<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Enums\TipoMatriculaEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NuevaMatriculaDesdeFichaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cicloConPeriodoAbierto(): Ciclo
    {
        return Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
    }

    public function test_grado_siguiente_para_programa_devuelve_el_de_orden_1_para_un_estudiante_nuevo(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado1 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 2]);
        $estudiante = Estudiante::factory()->create();

        $grado = $this->app->make(MatriculaService::class)->gradoSiguienteParaPrograma($estudiante, $programa);

        $this->assertSame($grado1->id, $grado->id);
    }

    public function test_grado_siguiente_para_programa_salta_los_semestres_ya_cursados(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado1 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        $grado2 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 2]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'grado_id' => $grado1->id]);

        $grado = $this->app->make(MatriculaService::class)->gradoSiguienteParaPrograma($estudiante, $programa);

        $this->assertSame($grado2->id, $grado->id);
    }

    public function test_matricular_desde_la_ficha_eligiendo_solo_el_programa(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = $this->cicloConPeriodoAbierto();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('mostrarFormNuevaMatricula')
            ->set('nuevaMatriculaProgramaId', (string) $programa->id)
            ->call('guardarNuevaMatricula')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'tipo_matricula' => TipoMatriculaEnum::REGULAR->value,
        ]);
    }

    public function test_sin_periodo_de_matricula_abierto_muestra_error(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $programa = ProgramaEstudio::factory()->create();
        Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('mostrarFormNuevaMatricula')
            ->set('nuevaMatriculaProgramaId', (string) $programa->id)
            ->call('guardarNuevaMatricula')
            ->assertHasErrors('nuevaMatriculaProgramaId');
    }

    public function test_un_docente_no_puede_matricular_desde_la_ficha(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $estudiante = Estudiante::factory()->create();

        $this->actingAs($docente);

        Volt::test('matricula.show', ['estudiante' => $estudiante])->assertForbidden();
    }
}
