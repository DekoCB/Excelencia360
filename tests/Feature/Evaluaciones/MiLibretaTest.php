<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
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
        $evaluacion = $evaluacionService->crear($horarioReciente, 'Evaluación', '2026-07-15');
        $evaluacionService->calificar($evaluacion, $estudiante, 17.0, null, null);
        $evaluacionService->publicar($evaluacion);

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
        $evaluacion = $evaluacionService->crear($horarioAnterior, 'Evaluación', '2026-03-15');
        $evaluacionService->calificar($evaluacion, $estudiante, 14.0, null, null);
        $evaluacionService->publicar($evaluacion);

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
}
