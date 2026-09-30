<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\TipoCursoEnum;
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

        Curso::factory()->hasAttached($gradoUno)->create(['nombre' => 'Comunicación', 'codigo' => 'COM-1']);

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
            ->set('gradoIds', [$grado->id])
            ->set('horas', '80')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Matemática',
            'codigo' => 'MAT-3',
        ]);
        $curso = Curso::query()->where('nombre', 'Matemática')->firstOrFail();
        $this->assertDatabaseHas('curso_grado', ['curso_id' => $curso->id, 'grado_id' => $grado->id]);
    }

    public function test_editar_un_curso_no_le_cambia_el_codigo_al_ajustar_nombre_o_grado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $gradoOriginal = Grado::factory()->create(['orden' => 1]);
        $otroGrado = Grado::factory()->create(['orden' => 5]);
        $curso = Curso::factory()->hasAttached($gradoOriginal)->create([
            'nombre' => 'Comunicación',
            'codigo' => 'COM-1',
        ]);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal', $curso->id)
            ->set('nombre', 'Comunicación Integral')
            ->set('gradoIds', [$otroGrado->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'nombre' => 'Comunicación Integral',
            'codigo' => 'COM-1',
        ]);
        $this->assertDatabaseHas('curso_grado', ['curso_id' => $curso->id, 'grado_id' => $otroGrado->id]);
        $this->assertDatabaseMissing('curso_grado', ['curso_id' => $curso->id, 'grado_id' => $gradoOriginal->id]);
    }

    public function test_genera_el_codigo_de_capacitacion_con_prefijo_cap(): void
    {
        $codigo = $this->service()->generarCodigoCapacitacion('Ofimática Básica');

        $this->assertSame('CAP-OFI', $codigo);
    }

    public function test_agrega_sufijo_al_codigo_de_capacitacion_si_ya_existe(): void
    {
        Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática', 'codigo' => 'CAP-OFI']);

        $codigo = $this->service()->generarCodigoCapacitacion('Ofimática Avanzada');

        $this->assertSame('CAP-OFI-2', $codigo);
    }

    public function test_de_capacitacion_solo_devuelve_cursos_de_ese_tipo(): void
    {
        Curso::factory()->capacitacion()->create(['nombre' => 'Primeros Auxilios']);
        Curso::factory()->create(['nombre' => 'Matemática']);

        $resultado = $this->service()->deCapacitacion();

        $this->assertCount(1, $resultado);
        $this->assertSame('Primeros Auxilios', $resultado->first()->nombre);
    }

    public function test_crear_un_curso_de_capacitacion_no_exige_semestre(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal')
            ->set('tipo', 'capacitacion')
            ->set('nombre', 'Gestión Educativa')
            ->set('horas', '128')
            ->set('documentoAutorizacion', 'R.G.G. N° 004-2026-GE360')
            ->call('guardar')
            ->assertHasNoErrors();

        $curso = Curso::query()->where('nombre', 'Gestión Educativa')->firstOrFail();
        $this->assertSame(TipoCursoEnum::CAPACITACION, $curso->tipo);
        $this->assertSame('R.G.G. N° 004-2026-GE360', $curso->documento_autorizacion);
        $this->assertSame(0, $curso->grados()->count());
    }

    public function test_un_curso_de_capacitacion_sin_horas_no_pasa_de_500_falla(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal')
            ->set('tipo', 'capacitacion')
            ->set('nombre', 'Curso Largo')
            ->set('horas', '1200')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Curso Largo', 'horas' => 1200]);
    }

    public function test_el_filtro_de_tipo_solo_muestra_cursos_de_ese_tipo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Curso::factory()->create(['nombre' => 'Curso Académico Visible']);
        Curso::factory()->capacitacion()->create(['nombre' => 'Curso Capacitación Visible']);

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->assertSee('Curso Académico Visible')
            ->assertDontSee('Curso Capacitación Visible')
            ->set('tipoFiltro', 'capacitacion')
            ->assertSee('Curso Capacitación Visible')
            ->assertDontSee('Curso Académico Visible')
            ->set('tipoFiltro', 'todos')
            ->assertSee('Curso Académico Visible')
            ->assertSee('Curso Capacitación Visible');
    }

    /**
     * Regresión: en el primer intento real de la migración de fusión
     * (producción, 2026-09-30) un nombre de curso de capacitación real de
     * 147 caracteres (resolución oficial larga) reventó el insert porque
     * cursos.nombre nació en 100 -- cursos_capacitacion.nombre permitía
     * 150. Se agrandó la columna a 150; este test cubre tanto el guardado
     * directo como el formulario.
     */
    public function test_un_curso_de_capacitacion_admite_un_nombre_largo_de_resolucion_oficial(): void
    {
        $nombreLargo = 'Psicología Educativa, Tutoría y Educación Inclusiva para el Acompañamiento Socioemocional y Desarrollo de Habilidades Blandas en Educación Superior';
        $this->assertSame(147, mb_strlen($nombreLargo));

        $curso = Curso::factory()->capacitacion()->create(['nombre' => $nombreLargo]);

        $this->assertSame($nombreLargo, $curso->fresh()->nombre);
    }

    public function test_crear_un_curso_de_capacitacion_con_nombre_largo_desde_el_formulario(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $nombreLargo = 'Psicología Educativa, Tutoría y Educación Inclusiva para el Acompañamiento Socioemocional y Desarrollo de Habilidades Blandas en Educación Superior';

        $this->actingAs($coordinador);

        Volt::test('academico.cursos.index')
            ->call('abrirModal')
            ->set('tipo', 'capacitacion')
            ->set('nombre', $nombreLargo)
            ->set('horas', '128')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => $nombreLargo, 'tipo' => 'capacitacion']);
    }
}
