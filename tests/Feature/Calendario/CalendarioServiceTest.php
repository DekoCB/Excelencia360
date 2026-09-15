<?php

namespace Tests\Feature\Calendario;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Calendario\Enums\CategoriaCalendarioEnum;
use App\Modules\Calendario\Enums\TipoEventoEnum;
use App\Modules\Calendario\Models\EventoCalendario;
use App\Modules\Calendario\Services\CalendarioService;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): CalendarioService
    {
        return $this->app->make(CalendarioService::class);
    }

    private function coordinador(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        return $usuario;
    }

    public function test_registrar_evento_crea_el_evento_con_los_datos_dados(): void
    {
        $creador = $this->coordinador();

        $evento = $this->service()->registrarEvento(
            $creador,
            TipoEventoEnum::REUNION,
            'Reunión de coordinación',
            'Sala de profesores',
            Carbon::parse('2026-09-10'),
            null,
            '15:00',
            '16:00',
        );

        $this->assertSame('Reunión de coordinación', $evento->titulo);
        $this->assertSame(TipoEventoEnum::REUNION, $evento->tipo);
        $this->assertSame($creador->id, $evento->creado_por);
        $this->assertDatabaseHas('eventos_calendario', ['titulo' => 'Reunión de coordinación']);
    }

    public function test_actualizar_evento_cambia_los_datos(): void
    {
        $evento = EventoCalendario::factory()->create(['titulo' => 'Original']);

        $actualizado = $this->service()->actualizarEvento(
            $evento,
            TipoEventoEnum::ACTO,
            'Título nuevo',
            null,
            Carbon::parse('2026-10-01'),
            null,
            null,
            null,
        );

        $this->assertSame('Título nuevo', $actualizado->titulo);
        $this->assertSame(TipoEventoEnum::ACTO, $actualizado->tipo);
    }

    public function test_eliminar_evento_lo_borra(): void
    {
        $evento = EventoCalendario::factory()->create();

        $this->service()->eliminarEvento($evento);

        $this->assertDatabaseMissing('eventos_calendario', ['id' => $evento->id]);
    }

    public function test_items_del_mes_incluye_un_evento_de_un_solo_dia(): void
    {
        EventoCalendario::factory()->create([
            'titulo' => 'Feriado local',
            'tipo' => TipoEventoEnum::FERIADO,
            'fecha_inicio' => '2026-09-15',
            'fecha_fin' => null,
        ]);

        $items = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));

        $eventos = $items->where('categoria', CategoriaCalendarioEnum::EVENTO);
        $this->assertCount(1, $eventos);
        $this->assertSame('2026-09-15', $eventos->first()->fecha->toDateString());
    }

    public function test_items_del_mes_expande_un_evento_de_varios_dias_en_un_item_por_dia(): void
    {
        EventoCalendario::factory()->create([
            'titulo' => 'Semana cultural',
            'fecha_inicio' => '2026-09-28',
            'fecha_fin' => '2026-10-02',
        ]);

        $itemsSeptiembre = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));
        $itemsOctubre = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-10-01'));

        $this->assertCount(3, $itemsSeptiembre->where('categoria', CategoriaCalendarioEnum::EVENTO));
        $this->assertCount(2, $itemsOctubre->where('categoria', CategoriaCalendarioEnum::EVENTO));
    }

    public function test_items_del_mes_no_incluye_eventos_fuera_del_mes(): void
    {
        EventoCalendario::factory()->create(['fecha_inicio' => '2026-08-31']);
        EventoCalendario::factory()->create(['fecha_inicio' => '2026-10-01']);

        $items = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));

        $this->assertCount(0, $items->where('categoria', CategoriaCalendarioEnum::EVENTO));
    }

    public function test_coordinador_ve_todas_las_evaluaciones_del_mes_incluyendo_borrador(): void
    {
        $horario = Horario::factory()->create();
        Evaluacion::factory()->create(['horario_id' => $horario->id, 'nombre' => 'Examen borrador', 'fecha' => '2026-09-12']);
        Evaluacion::factory()->publicada()->create(['horario_id' => $horario->id, 'nombre' => 'Examen publicado', 'fecha' => '2026-09-20']);

        $items = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));

        $this->assertCount(2, $items->where('categoria', CategoriaCalendarioEnum::EVALUACION));
    }

    public function test_docente_solo_ve_evaluaciones_de_sus_propios_horarios(): void
    {
        $docente1 = User::factory()->create();
        $docente1->assignRole(RolEnum::DOCENTE->value);
        $docente2 = User::factory()->create();
        $docente2->assignRole(RolEnum::DOCENTE->value);

        $horario1 = Horario::factory()->create(['docente_id' => $docente1->id]);
        $horario2 = Horario::factory()->create(['docente_id' => $docente2->id]);

        Evaluacion::factory()->create(['horario_id' => $horario1->id, 'nombre' => 'Del docente 1', 'fecha' => '2026-09-05']);
        Evaluacion::factory()->create(['horario_id' => $horario2->id, 'nombre' => 'Del docente 2', 'fecha' => '2026-09-06']);

        $items = $this->service()->itemsDelMes($docente1, Carbon::parse('2026-09-01'));
        $evaluaciones = $items->where('categoria', CategoriaCalendarioEnum::EVALUACION);

        $this->assertCount(1, $evaluaciones);
        $this->assertSame('Del docente 1', $evaluaciones->first()->titulo);
    }

    public function test_estudiante_solo_ve_evaluaciones_publicadas_de_su_grado_y_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);

        $estudianteUser = User::factory()->create();
        $estudianteUser->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $estudianteUser->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        Evaluacion::factory()->create(['horario_id' => $horario->id, 'nombre' => 'Sin publicar', 'fecha' => '2026-09-08']);
        Evaluacion::factory()->publicada()->create(['horario_id' => $horario->id, 'nombre' => 'Publicada', 'fecha' => '2026-09-09']);

        $items = $this->service()->itemsDelMes($estudianteUser, Carbon::parse('2026-09-01'));
        $evaluaciones = $items->where('categoria', CategoriaCalendarioEnum::EVALUACION);

        $this->assertCount(1, $evaluaciones);
        $this->assertSame('Publicada', $evaluaciones->first()->titulo);
    }

    public function test_apoderado_ve_evaluaciones_de_sus_hijos(): void
    {
        $ciclo = Ciclo::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);

        $hijo = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $hijo->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $apoderadoUser = User::factory()->create();
        $apoderadoUser->assignRole(RolEnum::APODERADO->value);
        Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'user_id' => $apoderadoUser->id]);

        Evaluacion::factory()->publicada()->create(['horario_id' => $horario->id, 'nombre' => 'Examen del hijo', 'fecha' => '2026-09-11']);

        $items = $this->service()->itemsDelMes($apoderadoUser, Carbon::parse('2026-09-01'));
        $evaluaciones = $items->where('categoria', CategoriaCalendarioEnum::EVALUACION);

        $this->assertCount(1, $evaluaciones);
        $this->assertSame('Examen del hijo', $evaluaciones->first()->titulo);
    }

    public function test_usuario_sin_alcance_academico_no_ve_clases_ni_evaluaciones_pero_si_eventos(): void
    {
        $horario = Horario::factory()->create();
        Evaluacion::factory()->publicada()->create(['horario_id' => $horario->id, 'fecha' => '2026-09-14']);
        EventoCalendario::factory()->create(['fecha_inicio' => '2026-09-14']);

        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $items = $this->service()->itemsDelMes($tesoreria, Carbon::parse('2026-09-01'));

        $this->assertCount(0, $items->where('categoria', CategoriaCalendarioEnum::EVALUACION));
        $this->assertCount(0, $items->where('categoria', CategoriaCalendarioEnum::CLASE));
        $this->assertCount(1, $items->where('categoria', CategoriaCalendarioEnum::EVENTO));
    }

    public function test_items_del_mes_incluye_una_clase_solo_en_la_fecha_del_dia_de_semana_correcto(): void
    {
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']);
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $horario->dias()->delete();
        $horario->dias()->create(['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '09:00', 'hora_fin' => '10:00']);

        $items = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));
        $clases = $items->where('categoria', CategoriaCalendarioEnum::CLASE);

        $this->assertGreaterThan(0, $clases->count());

        foreach ($clases as $clase) {
            $this->assertSame(DiaSemanaEnum::LUNES->numeroCarbon(), $clase->fecha->dayOfWeek);
            $this->assertSame(9, $clase->fecha->month);
        }
    }

    public function test_items_del_mes_excluye_clases_fuera_del_rango_del_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-16']);
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $horario->dias()->delete();

        $diaDentroDelCiclo = DiaSemanaEnum::deCarbon(Carbon::parse('2026-09-10')->dayOfWeek);
        $horario->dias()->create(['dia_semana' => $diaDentroDelCiclo, 'hora_inicio' => '09:00', 'hora_fin' => '10:00']);

        $items = $this->service()->itemsDelMes($this->coordinador(), Carbon::parse('2026-09-01'));
        $fechas = $items->where('categoria', CategoriaCalendarioEnum::CLASE)->pluck('fecha');

        $this->assertNotEmpty($fechas);

        foreach ($fechas as $fecha) {
            $this->assertTrue($fecha->between($ciclo->fecha_inicio, $ciclo->fecha_fin));
        }
    }
}
