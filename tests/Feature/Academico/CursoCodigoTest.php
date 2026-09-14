<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CursoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CursoCodigoTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CursoService
    {
        return $this->app->make(CursoService::class);
    }

    public function test_genera_el_codigo_con_tres_iniciales_y_el_orden_del_grado(): void
    {
        $grado = Grado::factory()->create(['orden' => 1]);

        $codigo = $this->service()->generarCodigo('Comunicación', $grado);

        $this->assertSame('COM-1', $codigo);
    }

    public function test_quita_tildes_al_generar_las_iniciales(): void
    {
        $grado = Grado::factory()->create(['orden' => 2]);

        $codigo = $this->service()->generarCodigo('Área Curricular', $grado);

        $this->assertSame('ARE-2', $codigo);
    }

    public function test_agrega_un_sufijo_si_el_codigo_base_ya_existe(): void
    {
        $gradoUno = Grado::factory()->create(['orden' => 1]);
        // No se persiste: generarCodigo() solo lee $grado->orden, así que
        // basta un grado en memoria con el mismo orden para forzar la
        // colisión de código sin violar la unicidad real de "orden".
        $otroGradoConMismoOrden = Grado::factory()->make(['orden' => 1]);

        Curso::factory()->create(['nombre' => 'Comunicación', 'codigo' => 'COM-1', 'grado_id' => $gradoUno->id]);

        $codigo = $this->service()->generarCodigo('Comunicación', $otroGradoConMismoOrden);

        $this->assertSame('COM-1-2', $codigo);
    }

    public function test_crear_un_curso_desde_el_formulario_le_asigna_codigo_automaticamente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $grado = Grado::factory()->create(['orden' => 3]);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal')
            ->set('nombre', 'Matemática')
            ->set('gradoId', (string) $grado->id)
            ->set('horas', '80')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Matemática',
            'codigo' => 'MAT-3',
            'grado_id' => $grado->id,
        ]);
    }

    public function test_editar_un_curso_no_le_cambia_el_codigo_al_ajustar_nombre_o_grado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $gradoOriginal = Grado::factory()->create(['orden' => 1]);
        $otroGrado = Grado::factory()->create(['orden' => 5]);
        $curso = Curso::factory()->create([
            'nombre' => 'Comunicación',
            'codigo' => 'COM-1',
            'grado_id' => $gradoOriginal->id,
        ]);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal', $curso->id)
            ->set('nombre', 'Comunicación Integral')
            ->set('gradoId', (string) $otroGrado->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'nombre' => 'Comunicación Integral',
            'codigo' => 'COM-1',
            'grado_id' => $otroGrado->id,
        ]);
    }
}
