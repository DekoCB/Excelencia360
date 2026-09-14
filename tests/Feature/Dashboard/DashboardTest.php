<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_direccion_carga_el_dashboard_sin_errores(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_docente_ve_sus_horarios_en_el_dashboard(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        Horario::factory()->count(2)->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis horarios')
            ->assertSee('2');
    }

    public function test_docente_ve_la_asistencia_de_sus_cursos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = Curso::factory()->create(['nombre' => 'Matematica']);
        $horario = Horario::factory()->create(['docente_id' => $docente->id, 'curso_id' => $curso->id]);
        Asistencia::factory()->create(['horario_id' => $horario->id, 'estado' => 'presente']);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistencia de mis cursos')
            ->assertSee('Matematica');
    }

    public function test_docente_ve_la_distribucion_de_notas_de_sus_evaluaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $evaluacion = Evaluacion::factory()->create(['horario_id' => $horario->id]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'nota_numerica' => 18]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Distribución de notas');
    }

    public function test_docente_sin_asistencia_ni_notas_no_ve_los_graficos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Asistencia de mis cursos')
            ->assertDontSee('Distribución de notas');
    }

    public function test_estudiante_al_dia_ve_su_proxima_cuota(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1, 'monto' => 100]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Próxima cuota')
            ->assertSee('100.00')
            ->assertDontSee('no está disponible');
    }

    public function test_estudiante_ve_su_rendimiento_mensual_en_notas(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $evaluacion = Evaluacion::factory()->publicada()->create(['fecha' => now()->format('Y-m-d')]);
        Calificacion::factory()->create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 16,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mi rendimiento');
    }

    public function test_estudiante_sin_notas_no_ve_el_grafico_de_rendimiento(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi rendimiento');
    }

    public function test_estudiante_no_ve_notas_de_evaluaciones_sin_publicar_en_su_rendimiento(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $evaluacion = Evaluacion::factory()->create(['fecha' => now()->format('Y-m-d')]);
        Calificacion::factory()->create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 16,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi rendimiento');
    }

    public function test_estudiante_bloqueado_ve_el_aviso_de_deuda(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->app->make(BloqueoAccesoService::class)->evaluarYDesbloquear($estudiante);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('no está disponible');
    }

    public function test_coordinador_ve_el_resumen_academico(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->count(3)->create(['estado' => 'activo']);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Estudiantes activos')
            ->assertSee('Evaluaciones sin publicar')
            ->assertSee('Ingresos aprobados')
            ->assertSee('Notificaciones');
    }

    public function test_tesoreria_ve_la_cola_de_aprobacion(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        Pago::factory()->count(2)->create(['estado' => 'pendiente']);

        $this->actingAs($tesoreria)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pagos por aprobar')
            ->assertSee('2')
            ->assertSee('Ingresos aprobados')
            ->assertSee('pago');
    }

    public function test_administrativo_ve_el_grafico_de_ingresos(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        Pago::factory()->create(['estado' => 'aprobado', 'monto' => 250, 'fecha_aprobacion' => now()]);

        $this->actingAs($administrativo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ingresos aprobados');
    }

    public function test_administrativo_no_ve_la_cola_de_aprobacion_de_tesoreria(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pagos por aprobar');
    }

    public function test_coordinador_sin_alertas_ve_notificaciones_vacias(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todo al día. No hay notificaciones pendientes.');
    }

    public function test_coordinador_ve_asistencia_por_grado_cuando_hay_registros(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $grado = Grado::factory()->create(['nombre' => '1ro de Secundaria']);
        $horario = Horario::factory()->create(['grado_id' => $grado->id]);
        Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estado' => 'presente',
        ]);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistencia por grado')
            ->assertSee('1ro de Secundaria');
    }

    public function test_estudiante_ve_solo_la_tarea_mas_proxima_de_cada_curso_en_vencimientos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $tareaService = $this->app->make(TareaService::class);
        $tareaProxima = $tareaService->crear($curso, [
            'titulo' => 'Tarea próxima',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $tareaService->crear($curso, [
            'titulo' => 'Tarea lejana',
            'descripcion' => null,
            'fecha_limite' => now()->addWeek(),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuario);

        $vencimientos = Volt::test('dashboard.index')->get('proximosVencimientos');

        $this->assertCount(1, $vencimientos);
        $this->assertSame($tareaProxima->id, $vencimientos->first()->id);
    }

    public function test_una_tarea_ya_entregada_no_aparece_en_vencimientos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $tareaService = $this->app->make(TareaService::class);
        $tarea = $tareaService->crear($curso, [
            'titulo' => 'Tarea ya entregada',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $tareaService->entregar($tarea, $estudiante, 'Mi respuesta', null);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Próximos vencimientos');
    }

    public function test_una_tarea_vencida_se_marca_como_vencida_en_el_widget(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Tarea vencida',
            'descripcion' => null,
            'fecha_limite' => now()->subDay(),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tarea vencida')
            ->assertSee('Vencida');
    }

    public function test_estudiante_sin_tareas_pendientes_no_ve_el_widget_de_vencimientos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Próximos vencimientos');
    }

    public function test_estudiante_ve_el_calendario_de_mis_tareas_en_el_dashboard(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis tareas — '.ucfirst(now()->translatedFormat('F Y')))
            ->assertDontSee('Tareas pendientes');
    }

    public function test_la_tarea_del_mes_actual_aparece_en_el_dia_correcto_del_calendario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo de mitad de mes',
            'descripcion' => null,
            'fecha_limite' => now()->startOfMonth()->addDays(14),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuario);

        $semanas = Volt::test('dashboard.index')->instance()->calendarioTareasSemanas();

        $diaEsperado = $tarea->fecha_limite->format('Y-m-d');
        $celda = collect($semanas)->flatten(1)->firstWhere('fecha', $diaEsperado);

        $this->assertNotNull($celda);
        $this->assertTrue($celda['tareas']->contains('id', $tarea->id));
    }

    public function test_seleccionar_un_dia_muestra_sus_tareas_debajo_del_calendario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Tarea del día seleccionado',
            'descripcion' => null,
            'fecha_limite' => now()->addDays(3),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuario);

        Volt::test('dashboard.index')
            ->call('seleccionarDiaCalendarioTareas', $tarea->fecha_limite->format('Y-m-d'))
            ->assertSee('Tarea del día seleccionado');
    }

    public function test_seleccionar_el_mismo_dia_dos_veces_lo_deselecciona(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('dashboard.index')
            ->call('seleccionarDiaCalendarioTareas', '2026-08-15')
            ->assertSet('diaCalendarioSeleccionado', '2026-08-15')
            ->call('seleccionarDiaCalendarioTareas', '2026-08-15')
            ->assertSet('diaCalendarioSeleccionado', null);
    }

    public function test_navegar_al_mes_siguiente_y_anterior_cambia_el_mes_mostrado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        $mesSiguienteEsperado = ucfirst(now()->addMonthNoOverflow()->translatedFormat('F Y'));

        Volt::test('dashboard.index')
            ->call('mesCalendarioTareasSiguiente')
            ->assertSee('Mis tareas — '.$mesSiguienteEsperado)
            ->call('mesCalendarioTareasAnterior')
            ->assertSee('Mis tareas — '.ucfirst(now()->translatedFormat('F Y')));
    }

    public function test_estudiante_ve_la_seccion_mis_evaluaciones_junto_al_calendario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $horario = Horario::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $evaluacion = Evaluacion::factory()->publicada()->create(['horario_id' => $horario->id]);
        Calificacion::factory()->create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 17,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis evaluaciones')
            ->assertSee($horario->curso->nombre)
            ->assertSee('17.00')
            ->assertDontSee('Mis notas');
    }

    public function test_estudiante_sin_calificaciones_ve_el_mensaje_vacio_en_mis_evaluaciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis evaluaciones')
            ->assertSee('Todavía no tienes calificaciones registradas.');
    }

    public function test_un_usuario_sin_rol_reconocido_ve_el_mensaje_de_bienvenida(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bienvenido a Excelencia 360');
    }
}
