<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\HorarioService;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Un horario de curso corto no tiene Período de matrícula ni Semestre
 * (ciclo_id/grado_id null, ver Horario::esDeCursoCorto()) y sus horas de
 * clase son opcionales -- decisión confirmada con el cliente tras ver que
 * el selector de "Curso" en Horarios mezclaba cursos académicos y de
 * capacitación sin distinción.
 */
class HorarioCursoCortoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actorCoordinador(): User
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        return $coordinador;
    }

    private function service(): HorarioService
    {
        return $this->app->make(HorarioService::class);
    }

    public function test_crear_un_horario_sin_ciclo_ni_grado_lo_marca_como_curso_corto(): void
    {
        $horario = $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        $this->assertTrue($horario->fresh()->esDeCursoCorto());
    }

    /**
     * Regresión: el aula virtual de un horario de curso corto se activaba
     * por horario_id (igual que un curso académico), pero
     * CursoVirtual::esDeCursoCorto() y el resto del sistema solo reconocen
     * un curso corto por curso_id -- quedaba creada pero invisible en todos
     * los listados de Aula Virtual.
     */
    public function test_crear_un_horario_de_curso_corto_activa_el_aula_virtual_por_curso_no_por_horario(): void
    {
        $curso = Curso::factory()->capacitacion()->create(['docente_id' => null]);
        $docente = User::factory()->create();

        $horario = $this->service()->crear([
            'curso_id' => $curso->id,
            'docente_id' => $docente->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        $cursoVirtual = CursoVirtual::query()->where('curso_id', $curso->id)->first();

        $this->assertNotNull($cursoVirtual);
        $this->assertNull($cursoVirtual->horario_id);
        $this->assertTrue($cursoVirtual->esDeCursoCorto());
        $this->assertFalse(CursoVirtual::query()->where('horario_id', $horario->id)->exists());
    }

    /**
     * Si el docente se asigna en el Horario (no editando el Curso
     * directamente), Curso::docente_id se sincroniza -- es el único campo
     * que CursoVirtual::docenteId() consulta para un curso corto, así que
     * sin esto el docente no podría gestionar su propia aula virtual.
     */
    public function test_crear_un_horario_de_curso_corto_sincroniza_el_docente_del_curso(): void
    {
        $curso = Curso::factory()->capacitacion()->create(['docente_id' => null]);
        $docente = User::factory()->create();

        $this->service()->crear([
            'curso_id' => $curso->id,
            'docente_id' => $docente->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        $this->assertSame($docente->id, $curso->fresh()->docente_id);
    }

    public function test_crear_sin_horas_definidas_guarda_los_dias_sin_hora(): void
    {
        $horario = $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
                ['dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        $this->assertCount(2, $horario->dias);
        $this->assertNull($horario->dias->first()->hora_inicio);
        $this->assertNull($horario->dias->first()->hora_fin);
    }

    public function test_dias_resumen_sin_horas_muestra_solo_los_nombres_de_dias(): void
    {
        $horario = $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
                ['dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        $this->assertSame('Sábado y Domingo', $horario->diasResumen());
    }

    public function test_dos_horarios_de_curso_corto_en_la_misma_aula_y_hora_chocan(): void
    {
        $aula = Aula::factory()->create();

        $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'],
            ],
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00'],
            ],
        ]);
    }

    public function test_un_horario_de_curso_corto_no_choca_con_uno_de_instituto_en_la_misma_aula_y_hora(): void
    {
        $aula = Aula::factory()->create();

        $this->service()->crear([
            'curso_id' => Curso::factory()->hasAttached(Grado::factory())->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'],
            ],
        ]);

        $horarioCursoCorto = $this->service()->crear([
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'],
            ],
        ]);

        $this->assertDatabaseHas('horarios', ['id' => $horarioCursoCorto->id]);
    }

    public function test_el_formulario_oculta_periodo_y_semestre_al_elegir_curso_corto(): void
    {
        $this->actingAs($this->actorCoordinador());

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('tipo', 'curso_corto')
            ->assertDontSee('Período de matrícula')
            ->assertDontSee('id="gradoId"');
    }

    public function test_el_selector_de_curso_solo_muestra_cursos_de_capacitacion_para_curso_corto(): void
    {
        $this->actingAs($this->actorCoordinador());

        $cursoAcademico = Curso::factory()->hasAttached(Grado::factory())->create(['nombre' => 'Matemática Avanzada']);
        $cursoCapacitacion = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica']);

        // El combobox de Curso (x-select-input) arma sus opciones con
        // @js() dentro de x-data, que escapa acentos como ó -- no
        // aparecen literales en el HTML, así que se verifica contra los
        // datos que recibe la vista en vez de con assertSee().
        $cursos = Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('tipo', 'curso_corto')
            ->viewData('cursos');

        $this->assertTrue($cursos->contains('id', $cursoCapacitacion->id));
        $this->assertFalse($cursos->contains('id', $cursoAcademico->id));
    }

    public function test_crear_un_horario_de_curso_corto_sin_definir_hora_desde_el_formulario(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->capacitacion()->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('tipo', 'curso_corto')
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['sabados_domingos'])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertTrue($horario->esDeCursoCorto());
        $this->assertCount(2, $horario->dias);
        $this->assertNull($horario->dias->first()->hora_inicio);
    }

    public function test_crear_un_horario_de_curso_corto_con_hora_en_formato_12h_la_guarda_en_24h(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->capacitacion()->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('tipo', 'curso_corto')
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['sabados_domingos'])
            ->set('conHoraDefinida', true)
            ->set('horaInicioHora12PorDia.sabado', '04')
            ->set('horaInicioMinutoPorDia.sabado', '00')
            ->set('horaInicioAmPmPorDia.sabado', 'PM')
            ->set('horaFinHora12PorDia.sabado', '05')
            ->set('horaFinMinutoPorDia.sabado', '00')
            ->set('horaFinAmPmPorDia.sabado', 'PM')
            ->set('horaInicioHora12PorDia.domingo', '09')
            ->set('horaInicioMinutoPorDia.domingo', '00')
            ->set('horaInicioAmPmPorDia.domingo', 'AM')
            ->set('horaFinHora12PorDia.domingo', '10')
            ->set('horaFinMinutoPorDia.domingo', '00')
            ->set('horaFinAmPmPorDia.domingo', 'AM')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $sabado = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::SABADO);
        $domingo = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::DOMINGO);

        $this->assertSame('16:00:00', $sabado->hora_inicio);
        $this->assertSame('17:00:00', $sabado->hora_fin);
        $this->assertSame('09:00:00', $domingo->hora_inicio);
        $this->assertSame('10:00:00', $domingo->hora_fin);
    }

    public function test_el_listado_agrupa_los_horarios_de_curso_corto_por_curso_en_vez_de_por_grado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica']);
        $this->service()->crear([
            'curso_id' => $curso->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => null,
            'grado_id' => null,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->assertSee('Curso corto: Ofimática Básica')
            ->assertSee('Ofimática Básica');
    }
}
