<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CursoCortoEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_listado_muestra_el_conteo_y_el_roster_del_curso_corto(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica']);
        $estudiante = Estudiante::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Ramos']);
        MatriculaCursoCorto::factory()->create([
            'curso_id' => $curso->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'activo',
        ]);
        MatriculaCursoCorto::factory()->create(['curso_id' => $curso->id, 'estado' => 'concluido']);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->set('tipoFiltro', 'capacitacion')
            ->assertSee('Ver estudiantes (1)')
            ->call('verEstudiantes', $curso->id)
            ->assertSee('Luis Ramos');
    }
}
