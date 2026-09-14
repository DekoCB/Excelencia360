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
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HorarioFormTest extends TestCase
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

    public function test_crea_un_horario_con_la_franja_lunes_y_miercoles(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->create();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(2, $horario->dias);

        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $miercoles = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::MIERCOLES);
        $this->assertSame('18:00:00', $lunes->hora_inicio);
        $this->assertSame('20:00:00', $lunes->hora_fin);
        $this->assertSame('18:00:00', $miercoles->hora_inicio);
        $this->assertSame('20:00:00', $miercoles->hora_fin);
    }

    public function test_muestra_el_mensaje_de_choque_de_aula_al_guardar(): void
    {
        $this->actingAs($this->actorCoordinador());

        $existente = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $existente->ciclo_id)
            ->set('gradoId', (string) $existente->grado_id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) $existente->aula_id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '19')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '21')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '19')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '21')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertSee('ya está ocupada');

        $this->assertSame(1, Horario::query()->count());
    }

    public function test_no_deja_guardar_sin_elegir_ninguna_franja(): void
    {
        $this->actingAs($this->actorCoordinador());

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradoId', (string) Grado::factory()->create()->id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->call('guardar')
            ->assertHasErrors('franjasSeleccionadas');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_crea_un_horario_combinando_dos_franjas_con_horas_distintas_por_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->create();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['lun_mie', 'mar_jue'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->set('horaInicioHoraPorDia.martes', '16')
            ->set('horaInicioMinutoPorDia.martes', '00')
            ->set('horaFinHoraPorDia.martes', '18')
            ->set('horaFinMinutoPorDia.martes', '00')
            ->set('horaInicioHoraPorDia.jueves', '16')
            ->set('horaInicioMinutoPorDia.jueves', '00')
            ->set('horaFinHoraPorDia.jueves', '18')
            ->set('horaFinMinutoPorDia.jueves', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(4, $horario->dias);

        $martes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::MARTES);
        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $this->assertSame('16:00:00', $martes->hora_inicio);
        $this->assertSame('18:00:00', $martes->hora_fin);
        $this->assertSame('18:00:00', $lunes->hora_inicio);
        $this->assertSame('20:00:00', $lunes->hora_fin);
    }

    public function test_arrastrar_una_tarjeta_en_la_pestana_editar_mueve_el_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $ciclo = Ciclo::factory()->create();
        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);
        $horarioDiaId = $horario->dias->first()->id;

        Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->set('vista', 'editar')
            ->call('moverDia', $horarioDiaId, 'sabado')
            ->assertHasNoErrors();

        // actualizar() borra y recrea las filas de "dias", así que el id
        // original ya no existe -- se relee el horario completo.
        $this->assertSame(DiaSemanaEnum::SABADO, $horario->fresh('dias')->dias->first()->dia_semana);
    }

    public function test_abrir_modal_editar_precarga_los_dias_reales_y_guardar_los_actualiza(): void
    {
        $this->actingAs($this->actorCoordinador());

        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('diasSueltosSeleccionados', ['lunes'])
            ->assertSet('horaInicioHoraPorDia.lunes', '18')
            ->set('horaFinHoraPorDia.lunes', '21')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('21:00:00', $horario->fresh('dias')->dias->first()->hora_fin);
    }

    public function test_el_boton_editar_en_la_lista_abre_el_modal_precargado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $horario->ciclo_id)
            ->assertSee('Editar')
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('mostrarModal', true)
            ->assertSet('editandoId', $horario->id)
            ->assertSet('cursoId', (string) $horario->curso_id);
    }

    public function test_el_listado_agrupa_los_horarios_por_franja_y_luego_por_grado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $ciclo = Ciclo::factory()->create();
        $gradoA = Grado::factory()->create(['nombre' => 'Grado 1 - Mayores']);
        $gradoB = Grado::factory()->create(['nombre' => 'Grado 2 - Mayores']);
        $service = $this->app->make(HorarioService::class);

        $service->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $gradoA->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        $service->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $gradoB->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00'],
            ],
        ]);

        $html = Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->html();

        // Se compara la PRIMERA aparición de cada texto: el listado se
        // renderiza antes que el formulario "Nuevo horario" (que repite los
        // mismos nombres de grado y franja en sus selects), así que si el
        // orden real fuera otro, alguna de estas comparaciones fallaría.
        $posicionLunMie = mb_strpos($html, 'Lunes y Miércoles');
        $posicionGradoA = mb_strpos($html, 'Grado 1 - Mayores');
        $posicionDomingo = mb_strpos($html, 'Domingo');
        $posicionGradoB = mb_strpos($html, 'Grado 2 - Mayores');

        $this->assertLessThan($posicionGradoA, $posicionLunMie);
        $this->assertLessThan($posicionDomingo, $posicionGradoA);
        $this->assertLessThan($posicionGradoB, $posicionDomingo);
    }
}
