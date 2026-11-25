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

    /**
     * @return array{dia: string, horaInicioHora: string, horaInicioMinuto: string, horaInicioAmPm: string, horaFinHora: string, horaFinMinuto: string, horaFinAmPm: string}
     */
    private function bloqueInstituto(string $dia, string $horaInicio, string $minutoInicio, string $horaFin, string $minutoFin): array
    {
        return [
            'dia' => $dia,
            'horaInicioHora' => $horaInicio,
            'horaInicioMinuto' => $minutoInicio,
            'horaInicioAmPm' => '',
            'horaFinHora' => $horaFin,
            'horaFinMinuto' => $minutoFin,
            'horaFinAmPm' => '',
        ];
    }

    public function test_crea_un_horario_con_bloques_sabado_y_domingo(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('bloques', [
                $this->bloqueInstituto('sabado', '09', '00', '11', '00'),
                $this->bloqueInstituto('domingo', '09', '00', '11', '00'),
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(2, $horario->dias);

        $sabado = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::SABADO);
        $domingo = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::DOMINGO);
        $this->assertSame('09:00:00', $sabado->hora_inicio);
        $this->assertSame('11:00:00', $sabado->hora_fin);
        $this->assertSame('09:00:00', $domingo->hora_inicio);
        $this->assertSame('11:00:00', $domingo->hora_fin);
    }

    public function test_crea_un_horario_con_dos_bloques_el_mismo_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('bloques', [
                $this->bloqueInstituto('lunes', '08', '00', '10', '00'),
                $this->bloqueInstituto('lunes', '14', '00', '16', '00'),
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $bloquesDelLunes = $horario->dias->where('dia_semana', DiaSemanaEnum::LUNES)->sortBy('hora_inicio')->values();

        $this->assertCount(2, $bloquesDelLunes);
        $this->assertSame('08:00:00', $bloquesDelLunes[0]->hora_inicio);
        $this->assertSame('10:00:00', $bloquesDelLunes[0]->hora_fin);
        $this->assertSame('14:00:00', $bloquesDelLunes[1]->hora_inicio);
        $this->assertSame('16:00:00', $bloquesDelLunes[1]->hora_fin);
    }

    public function test_no_deja_guardar_dos_bloques_del_mismo_dia_si_se_cruzan_en_horas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('bloques', [
                $this->bloqueInstituto('lunes', '08', '00', '10', '00'),
                $this->bloqueInstituto('lunes', '09', '00', '11', '00'),
            ])
            ->call('guardar')
            ->assertSee('se cruzan en horas');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_el_minuto_es_libre_y_no_se_limita_a_cuartos_de_hora(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('bloques', [$this->bloqueInstituto('lunes', '08', '7', '10', '52')])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertSame('08:07:00', $horario->dias->first()->hora_inicio);
        $this->assertSame('10:52:00', $horario->dias->first()->hora_fin);
    }

    public function test_instituto_puede_crearse_sin_horas_definidas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('conHoraDefinida', false)
            ->set('franjasSeleccionadas', ['sabados_domingos'])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(2, $horario->dias);
        $this->assertNull($horario->dias->first()->hora_inicio);
        $this->assertFalse($horario->fresh()->tieneHorasDefinidas());
    }

    public function test_instituto_sigue_exigiendo_al_menos_un_dia_aunque_no_tenga_horas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('conHoraDefinida', false)
            ->call('guardar')
            ->assertHasErrors('franjasSeleccionadas');
    }

    public function test_curso_corto_con_horario_libre_no_pide_dias_ni_horas(): void
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
            ->set('horarioLibre', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(0, $horario->dias);
    }

    public function test_muestra_el_mensaje_de_choque_de_aula_al_guardar(): void
    {
        $this->actingAs($this->actorCoordinador());

        $gradoExistente = Grado::factory()->create();
        $existente = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => $gradoExistente->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $existente->ciclo_id)
            ->set('programaEstudioId', (string) $gradoExistente->programa_estudio_id)
            ->set('gradoId', (string) $existente->grado_id)
            ->set('cursoId', (string) Curso::factory()->hasAttached($gradoExistente)->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) $existente->aula_id)
            ->set('bloques', [
                $this->bloqueInstituto('sabado', '19', '00', '21', '00'),
                $this->bloqueInstituto('domingo', '19', '00', '21', '00'),
            ])
            ->call('guardar')
            ->assertSee('ya está ocupada');

        $this->assertSame(1, Horario::query()->count());
    }

    public function test_no_deja_guardar_un_curso_que_no_pertenece_al_semestre_elegido(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $otroGrado = Grado::factory()->create();
        // El curso está vinculado a $otroGrado, no a $grado.
        $curso = Curso::factory()->hasAttached($otroGrado)->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('bloques', [
                $this->bloqueInstituto('sabado', '18', '00', '20', '00'),
                $this->bloqueInstituto('domingo', '18', '00', '20', '00'),
            ])
            ->call('guardar')
            ->assertHasErrors('cursoId');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_no_deja_guardar_sin_elegir_ninguna_franja(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('conHoraDefinida', false)
            ->call('guardar')
            ->assertHasErrors('franjasSeleccionadas');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_no_deja_guardar_con_hora_definida_sin_ningun_bloque(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('bloques', [])
            ->call('guardar')
            ->assertHasErrors('bloques');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_crea_un_horario_combinando_varios_dias_con_horas_distintas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('programaEstudioId', (string) $grado->programa_estudio_id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('bloques', [
                $this->bloqueInstituto('lunes', '18', '00', '20', '00'),
                $this->bloqueInstituto('martes', '18', '00', '20', '00'),
                $this->bloqueInstituto('miercoles', '18', '00', '20', '00'),
                $this->bloqueInstituto('jueves', '18', '00', '20', '00'),
                $this->bloqueInstituto('viernes', '18', '00', '20', '00'),
                $this->bloqueInstituto('sabado', '10', '00', '12', '00'),
                $this->bloqueInstituto('domingo', '10', '00', '12', '00'),
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(7, $horario->dias);

        $sabado = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::SABADO);
        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $this->assertSame('10:00:00', $sabado->hora_inicio);
        $this->assertSame('12:00:00', $sabado->hora_fin);
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

    public function test_abrir_modal_editar_precarga_los_bloques_reales_y_guardar_los_actualiza(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->hasAttached($grado)->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => $grado->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('bloques.0.dia', 'lunes')
            ->assertSet('bloques.0.horaInicioHora', '18')
            ->set('bloques.0.horaFinHora', '21')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('21:00:00', $horario->fresh('dias')->dias->first()->hora_fin);
    }

    public function test_editar_puede_agregar_un_segundo_bloque_el_mismo_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();
        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->hasAttached($grado)->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => $grado->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModalEditar', $horario->id)
            ->call('agregarBloque')
            ->set('bloques.1', $this->bloqueInstituto('lunes', '14', '00', '16', '00'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertCount(2, $horario->fresh('dias')->dias);
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
                ['dia_semana' => DiaSemanaEnum::MARTES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::JUEVES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::VIERNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        $service->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $gradoB->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::SABADO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00'],
                ['dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00'],
            ],
        ]);

        $html = Volt::test('academico.horarios.index')
            ->set('vista', 'lista')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->html();

        // Se compara la PRIMERA aparición de cada texto: el listado se
        // renderiza antes que el formulario "Nuevo horario" (que repite los
        // mismos nombres de grado y franja en sus selects), así que si el
        // orden real fuera otro, alguna de estas comparaciones fallaría.
        $posicionLunesViernes = mb_strpos($html, 'Lunes a Viernes');
        $posicionGradoA = mb_strpos($html, 'Grado 1 - Mayores');
        $posicionSabadosDomingos = mb_strpos($html, 'Sábados y Domingos');
        $posicionGradoB = mb_strpos($html, 'Grado 2 - Mayores');

        $this->assertLessThan($posicionGradoA, $posicionLunesViernes);
        $this->assertLessThan($posicionSabadosDomingos, $posicionGradoA);
        $this->assertLessThan($posicionGradoB, $posicionSabadosDomingos);
    }
}
