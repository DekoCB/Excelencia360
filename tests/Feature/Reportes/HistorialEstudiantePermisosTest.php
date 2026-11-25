<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\RolEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Pago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HistorialEstudiantePermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_el_historial_de_estudiante(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('historial-estudiante.index'))
            ->assertOk();
    }

    public function test_direccion_puede_ver_el_historial_de_estudiante(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('historial-estudiante.index'))
            ->assertOk();
    }

    public function test_docente_no_puede_ver_el_historial_de_estudiante(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_tesoreria_no_puede_ver_el_historial_de_estudiante(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_administrativo_no_puede_ver_el_historial_de_estudiante(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_estudiante_puede_ver_su_propio_historial_sin_buscador(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id, 'dni' => '55667788']);

        $this->actingAs($usuario);

        Volt::test('historial-estudiante.index')
            ->assertDontSee('Buscar estudiante')
            ->assertSee('DNI 55667788');
    }

    public function test_estudiante_sin_ficha_no_puede_ver_el_historial(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_estudiante_no_puede_forzar_ver_el_historial_de_otro_estudiante(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $otro = Estudiante::factory()->create();

        $this->actingAs($usuario);

        Volt::test('historial-estudiante.index')
            ->call('seleccionarEstudiante', $otro->id, $otro->nombreCompleto())
            ->assertForbidden();
    }

    public function test_buscar_por_dni_muestra_al_estudiante_en_la_lista_de_resultados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', '55667788')
            ->assertSee('Carla Robles Díaz');
    }

    public function test_buscar_por_nombre_muestra_al_estudiante_en_la_lista_de_resultados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Robles')
            ->assertSee('Carla Robles Díaz')
            ->assertSee('55667788');
    }

    public function test_buscar_un_termino_sin_coincidencias_muestra_un_mensaje_claro(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Nadie Existe')
            ->assertSee('No se encontraron estudiantes');
    }

    public function test_elegir_un_resultado_muestra_el_historial_del_estudiante(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Robles')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertHasNoErrors()
            ->assertSee('DNI 55667788');
    }

    public function test_el_historial_muestra_el_nombre_del_periodo_academico_de_cada_matricula(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667799', 'nombres' => 'Marco', 'apellidos' => 'Villar Soto']);
        $ciclo = Ciclo::factory()->create(['nombre' => '2026-1']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Villar Soto')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertHasNoErrors()
            ->assertSee('2026-1');
    }

    public function test_el_historial_muestra_el_detalle_de_pagos_ya_cobrados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667700', 'nombres' => 'Elmer', 'apellidos' => 'Palomino Ochoa']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'monto' => 120]);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Palomino Ochoa')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertHasNoErrors()
            ->assertSee('Detalle de pagos')
            ->assertSee('S/ 120.00');
    }

    public function test_la_pestana_financiero_muestra_pagos_sin_los_demas_bloques(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667701', 'nombres' => 'Flor', 'apellidos' => 'Araujo Dias']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'monto' => 90]);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Araujo Dias')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('tipo', 'financiero')
            ->assertSee('Situación de pagos')
            ->assertSee('S/ 90.00')
            ->assertDontSee('Semestres cursados')
            ->assertDontSee('Subidos al matricularse');
    }

    public function test_la_pestana_general_sigue_mostrando_todo_junto(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667702', 'nombres' => 'Gino', 'apellidos' => 'Salazar Vidal']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'monto' => 70]);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Salazar Vidal')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertSee('Semestres cursados')
            ->assertSee('Situación de pagos')
            ->assertSee('Documentos');
    }

    public function test_seleccionar_estudiante_preselecciona_la_libreta_del_ciclo_mas_reciente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667711', 'nombres' => 'Hernán', 'apellidos' => 'Ochoa Rojas']);

        $matriculaVieja = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada', 'fecha_matricula' => now()->subYear()]);
        $matriculaReciente = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada', 'fecha_matricula' => now()]);

        foreach ([$matriculaVieja, $matriculaReciente] as $matricula) {
            $horario = Horario::factory()->create(['grado_id' => $matricula->grado_id, 'ciclo_id' => $matricula->ciclo_id]);
            $evaluacion = Evaluacion::factory()->create(['horario_id' => $horario->id, 'estado' => 'publicada']);
            Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id, 'nota_numerica' => 15]);
        }

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Ochoa Rojas')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertSet('cicloLibretaId', $matriculaReciente->ciclo_id)
            ->assertSee('Libreta de notas')
            ->assertSee('Exportar boleta de notas (PDF)');
    }

    public function test_cambiar_el_ciclo_de_la_libreta_filtra_las_notas_mostradas(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667712', 'nombres' => 'Carmen', 'apellidos' => 'Torres Rojas']);

        $matriculaUno = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada', 'fecha_matricula' => now()->subYear()]);
        $matriculaDos = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada', 'fecha_matricula' => now()]);

        $horarioUno = Horario::factory()->create(['grado_id' => $matriculaUno->grado_id, 'ciclo_id' => $matriculaUno->ciclo_id]);
        $this->calificarCompleto($horarioUno, $estudiante, 12.0);

        $horarioDos = Horario::factory()->create(['grado_id' => $matriculaDos->grado_id, 'ciclo_id' => $matriculaDos->ciclo_id]);
        $this->calificarCompleto($horarioDos, $estudiante, 18.0);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertSet('cicloLibretaId', $matriculaDos->ciclo_id)
            ->assertSee('18.00')
            ->set('cicloLibretaId', $matriculaUno->ciclo_id)
            ->assertSee('12.00');
    }

    public function test_exportar_libreta_pdf_descarga_el_pdf_del_ciclo_elegido(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667713', 'nombres' => 'Julio', 'apellidos' => 'Rojas Medina']);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada']);

        $this->actingAs($coordinador);

        $testable = Volt::test('historial-estudiante.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('cicloLibretaId', $matricula->ciclo_id)
            ->call('exportarLibretaPdf');

        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }

    public function test_el_enlace_de_historial_aparece_en_el_menu_para_coordinador(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Historial de estudiante');
    }

    public function test_el_enlace_de_historial_no_aparece_en_el_menu_para_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Historial de estudiante');
    }

    /**
     * Califica los 4 componentes obligatorios de la fórmula (Unidad 1,
     * Unidad 2, Asistencia, Examen Final) con la misma nota, para que el
     * promedio final resultante sea exactamente esa nota.
     */
    private function calificarCompleto(Horario $horario, Estudiante $estudiante, float $nota): void
    {
        foreach ([
            ['rol' => RolEvaluacionEnum::CRITERIO, 'unidad' => 1],
            ['rol' => RolEvaluacionEnum::CRITERIO, 'unidad' => 2],
            ['rol' => RolEvaluacionEnum::ASISTENCIA, 'unidad' => null],
            ['rol' => RolEvaluacionEnum::EXAMEN_FINAL, 'unidad' => null],
        ] as $datos) {
            $evaluacion = Evaluacion::factory()->create([
                'horario_id' => $horario->id,
                'rol' => $datos['rol'],
                'unidad' => $datos['unidad'],
                'estado' => 'publicada',
            ]);
            Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id, 'nota_numerica' => $nota]);
        }
    }
}
