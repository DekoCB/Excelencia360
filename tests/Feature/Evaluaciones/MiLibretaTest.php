<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\RolEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MiLibretaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_estudiante_ve_su_libreta_del_ciclo_mas_reciente_por_defecto(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cicloAnterior = Ciclo::factory()->create(['nombre' => 'Enero - Junio 2026', 'fecha_inicio' => '2026-01-01']);
        $cicloReciente = Ciclo::factory()->create(['nombre' => 'Julio - Diciembre 2026', 'fecha_inicio' => '2026-07-01']);

        $horarioAnterior = Horario::factory()->create(['ciclo_id' => $cicloAnterior->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horarioAnterior->grado_id,
            'ciclo_id' => $cicloAnterior->id,
        ]);

        $horarioReciente = Horario::factory()->create(['ciclo_id' => $cicloReciente->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horarioReciente->grado_id,
            'ciclo_id' => $cicloReciente->id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $cursoReciente = CursoVirtual::factory()->create(['horario_id' => $horarioReciente->id]);
        $this->calificarCompleto($evaluacionService, $cursoReciente, $estudiante, 17.0);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.mi-libreta'))
            ->assertOk()
            ->assertSee('Julio - Diciembre 2026')
            ->assertSee($horarioReciente->curso->nombre)
            ->assertSee('17.00');
    }

    public function test_un_estudiante_con_un_solo_ciclo_no_ve_el_selector(): void
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

        $this->actingAs($usuario)
            ->get(route('evaluaciones.mi-libreta'))
            ->assertOk()
            ->assertDontSee('id="cicloId"', false);
    }

    public function test_un_estudiante_puede_cambiar_de_ciclo_con_el_selector(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cicloAnterior = Ciclo::factory()->create(['nombre' => 'Enero - Junio 2026', 'fecha_inicio' => '2026-01-01']);
        $cicloReciente = Ciclo::factory()->create(['nombre' => 'Julio - Diciembre 2026', 'fecha_inicio' => '2026-07-01']);

        $horarioAnterior = Horario::factory()->create(['ciclo_id' => $cicloAnterior->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horarioAnterior->grado_id,
            'ciclo_id' => $cicloAnterior->id,
        ]);

        $horarioReciente = Horario::factory()->create(['ciclo_id' => $cicloReciente->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horarioReciente->grado_id,
            'ciclo_id' => $cicloReciente->id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $cursoAnterior = CursoVirtual::factory()->create(['horario_id' => $horarioAnterior->id]);
        $this->calificarCompleto($evaluacionService, $cursoAnterior, $estudiante, 14.0);

        $this->actingAs($usuario);

        Volt::test('evaluaciones.mi-libreta')
            ->set('cicloId', (string) $cicloAnterior->id)
            ->assertSee($horarioAnterior->curso->nombre)
            ->assertSee('14.00');
    }

    public function test_un_estudiante_sin_matricula_aprobada_ve_el_mensaje_vacio(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.mi-libreta'))
            ->assertOk()
            ->assertSee('no tienes una matrícula aprobada');
    }

    public function test_un_docente_no_puede_ver_mi_libreta_porque_no_es_estudiante(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('evaluaciones.mi-libreta'))
            ->assertForbidden();
    }

    public function test_un_estudiante_puede_generar_su_libreta_desde_mi_libreta(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($usuario);

        Volt::test('evaluaciones.mi-libreta')
            ->call('generar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('libretas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $horario->ciclo_id]);
    }

    /**
     * Califica los 4 componentes obligatorios de la fórmula (Unidad 1,
     * Unidad 2, Asistencia, Examen Final) con la misma nota, para que el
     * promedio final resultante sea exactamente esa nota (0.2+0.2+0.2+0.4
     * de un mismo valor = ese valor).
     */
    private function calificarCompleto(EvaluacionService $service, CursoVirtual $curso, Estudiante $estudiante, float $nota): void
    {
        foreach ([
            ['Criterio U1', RolEvaluacionEnum::CRITERIO, 1],
            ['Criterio U2', RolEvaluacionEnum::CRITERIO, 2],
            ['Asistencia', RolEvaluacionEnum::ASISTENCIA, null],
            ['Examen Final', RolEvaluacionEnum::EXAMEN_FINAL, null],
        ] as [$nombre, $rol, $unidad]) {
            $evaluacion = $service->crear($curso, $nombre, '2026-07-01', TipoEvaluacionEnum::FISICO, $rol, $unidad);
            $service->calificar($evaluacion, $estudiante, $nota, null, null);
            $service->publicar($evaluacion);
        }
    }
}
