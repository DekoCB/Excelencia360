<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Academico\Models\SeccionAcademica;
use App\Modules\Academico\Services\ProgramaEstudioService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProgramaEstudioDetalleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): ProgramaEstudioService
    {
        return $this->app->make(ProgramaEstudioService::class);
    }

    public function test_cursos_de_trae_solo_los_cursos_de_los_grados_del_programa(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $cursoDelPrograma = Curso::factory()->hasAttached($grado)->create();
        Curso::factory()->hasAttached(Grado::factory())->create();

        $cursos = $this->service()->cursosDe($programa);

        $this->assertCount(1, $cursos);
        $this->assertSame($cursoDelPrograma->id, $cursos->first()->id);
    }

    public function test_estudiantes_de_trae_los_matriculados_en_algun_grado_del_programa(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'grado_id' => $grado->id]);

        Estudiante::factory()->create();

        $estudiantes = $this->service()->estudiantesDe($programa);

        $this->assertCount(1, $estudiantes);
        $this->assertSame($estudiante->id, $estudiantes->first()->id);
    }

    public function test_secciones_con_estudiantes_cuenta_por_letra(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $seccionA = SeccionAcademica::query()->where('letra', 'A')->first();

        Matricula::factory()->count(2)->create(['grado_id' => $grado->id, 'seccion_academica_id' => $seccionA->id]);

        $secciones = $this->service()->seccionesConEstudiantes($programa);

        $this->assertCount(1, $secciones);
        $this->assertSame('A', $secciones->first()['letra']);
        $this->assertSame(2, $secciones->first()['estudiantes']);
    }

    public function test_la_pantalla_muestra_el_detalle_al_expandir(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $programa = ProgramaEstudio::factory()->create(['nombre' => 'Contabilidad']);
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id]);
        $curso = Curso::factory()->hasAttached($grado)->create(['nombre' => 'Costos I']);
        $estudiante = Estudiante::factory()->create(['nombres' => 'Rosa', 'apellidos' => 'Pérez']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'grado_id' => $grado->id]);

        $this->actingAs($coordinador);

        Volt::test('academico.programas-estudio.index')
            ->call('alternarDetalle', $programa->id)
            ->assertSee('Costos I')
            ->assertSee('Rosa Pérez');
    }
}
