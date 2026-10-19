<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportesPermisosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function horarioConFranja(FranjaHorarioEnum $franja, array $atributos = []): Horario
    {
        $horario = Horario::factory()->create($atributos);
        $horario->dias()->delete();

        foreach ($franja->dias() as $dia) {
            $horario->dias()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '18:00:00',
                'hora_fin' => '20:00:00',
            ]);
        }

        return $horario->fresh(['dias']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_el_constructor_de_reportes(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Académico')
            ->assertSee('Deudores')
            ->assertDontSee('Mis evaluaciones');
    }

    public function test_docente_solo_ve_su_propio_tipo_de_reporte(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Mis evaluaciones')
            ->assertDontSee('Financiero');
    }

    /**
     * Dirección tiene todos los permisos vía el comodín '*', lo que
     * incluiría `reportes.propios` -- pero "Mis evaluaciones" es un reporte
     * pensado para el propio docente (sus horarios), así que no debe
     * ofrecerse en el selector a ningún rol superior que no sea Docente.
     */
    public function test_direccion_no_ve_mis_evaluaciones_en_el_selector_de_reportes(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertDontSee('Mis evaluaciones');
    }

    public function test_un_estudiante_no_puede_ver_reportes(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('reportes.index'))
            ->assertForbidden();
    }

    public function test_exportar_a_pdf_no_lanza_el_error_de_livewire_con_dompdf(): void
    {
        // Regresión: Pdf::loadView(...)->download() devuelve un
        // Illuminate\Http\Response plano, que Livewire no reconoce como
        // descarga de archivo (solo StreamedResponse/BinaryFileResponse), así
        // que intentaba serializar los bytes binarios del PDF como valor de
        // retorno normal y json_encode() truena con "Malformed UTF-8
        // characters". Ver el fix en reportes/index.blade.php.
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        $testable = Volt::test('reportes.index')
            ->set('tipo', 'academico')
            ->call('exportarPdf');

        // Si Livewire reconoce la respuesta como descarga de archivo (el fix),
        // la codifica como efecto "download" en vez de intentar serializarla
        // como valor de retorno normal.
        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }

    public function test_el_filtro_de_horario_muestra_las_3_franjas_institucionales(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        // x-select-input embebe sus opciones como JSON con unicode escapado
        // (Miércoles -> Miércoles), así que se verifica el HTML crudo
        // en vez de assertSee con texto acentuado.
        $html = Volt::test('reportes.index')
            ->set('tipo', 'academico')
            ->html();

        $this->assertStringContainsString('lun_mie', $html);
        $this->assertStringContainsString('mar_jue', $html);
        $this->assertStringContainsString('domingo', $html);
    }

    public function test_filtrar_el_reporte_academico_por_franja_solo_trae_esa_franja(): void
    {
        $horarioLunes = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE, [
            'curso_id' => Curso::factory()->create(['nombre' => 'Curso Lunes']),
        ]);
        $horarioMartes = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE, [
            'curso_id' => Curso::factory()->create(['nombre' => 'Curso Martes']),
        ]);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioLunes->id])->id]);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioMartes->id])->id]);

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);
        Volt::test('reportes.index')
            ->set('tipo', 'academico')
            ->assertSee('Curso Lunes')
            ->assertSee('Curso Martes')
            ->set('franja', FranjaHorarioEnum::LUN_MIE->value)
            ->assertSee('Curso Lunes')
            ->assertDontSee('Curso Martes');
    }

    public function test_docente_solo_ve_sus_propios_horarios_en_mis_evaluaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horarioPropio = Horario::factory()->create([
            'docente_id' => $docente->id,
            'curso_id' => Curso::factory()->create(['nombre' => 'Curso Del Docente']),
        ]);
        $horarioAjeno = Horario::factory()->create([
            'curso_id' => Curso::factory()->create(['nombre' => 'Curso Ajeno']),
        ]);
        Evaluacion::factory()->create(['horario_id' => $horarioPropio->id]);
        Evaluacion::factory()->create(['horario_id' => $horarioAjeno->id]);

        $this->actingAs($docente);
        Volt::test('reportes.index')
            ->set('tipo', 'propio')
            ->assertSee('Curso Del Docente')
            ->assertDontSee('Curso Ajeno');
    }

    public function test_cambiar_el_tipo_de_reporte_reinicia_el_filtro_de_horario(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('reportes.index')
            ->set('tipo', 'academico')
            ->set('franja', FranjaHorarioEnum::LUN_MIE->value)
            ->assertSet('franja', FranjaHorarioEnum::LUN_MIE->value)
            ->set('tipo', 'financiero')
            ->assertSet('franja', '');
    }

    public function test_el_filtro_de_fecha_fue_reemplazado_por_grupo_grado_y_curso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        $html = Volt::test('reportes.index')->html();

        $this->assertStringContainsString('id="cicloId"', $html);
        $this->assertStringContainsString('id="gradoId"', $html);
        $this->assertStringContainsString('id="cursoId"', $html);
        $this->assertStringNotContainsString('id="desde"', $html);
        $this->assertStringNotContainsString('id="hasta"', $html);
    }

    public function test_elegir_un_grupo_reinicia_grado_y_curso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('reportes.index')
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', '1')
            ->set('cicloId', (string) $ciclo->id)
            ->assertSet('gradoId', '')
            ->assertSet('cursoId', '');
    }

    public function test_elegir_un_grado_reinicia_curso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $grado = Grado::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('reportes.index')
            ->set('cursoId', '1')
            ->set('gradoId', (string) $grado->id)
            ->assertSet('cursoId', '');
    }

    public function test_filtrar_por_grupo_reduce_el_reporte_de_matricula(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        $estudianteA = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe']);
        $estudianteB = Estudiante::factory()->create(['nombres' => 'Beto', 'apellidos' => 'Salas']);
        Matricula::factory()->create(['estudiante_id' => $estudianteA->id, 'grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Matricula::factory()->create(['estudiante_id' => $estudianteB->id, 'grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('reportes.index')
            ->set('tipo', 'matricula')
            ->assertSee('Ana')
            ->assertSee('Beto')
            ->set('cicloId', (string) $horarioA->ciclo_id)
            ->assertSee('Ana')
            ->assertDontSee('Beto');
    }
}
