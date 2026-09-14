<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EvaluacionesBloqueoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function estudianteBloqueado(): array
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->app->make(BloqueoAccesoService::class)->evaluarYDesbloquear($estudiante);

        return [$usuario, $estudiante, $horario];
    }

    public function test_un_estudiante_bloqueado_no_ve_sus_notas(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteBloqueado();

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('no están disponibles')
            ->assertDontSee('18.00');
    }

    public function test_un_estudiante_no_bloqueado_si_ve_sus_notas(): void
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

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('18.00')
            ->assertDontSee('no están disponibles');
    }

    public function test_un_estudiante_bloqueado_no_puede_generar_su_libreta(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteBloqueado();
        $ciclo = $horario->ciclo;

        $this->actingAs($usuario)
            ->get(route('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $ciclo]))
            ->assertOk()
            ->assertSee('libreta no está disponible');
    }

    private function estudianteConUnaCuotaVencidaEnCicloActivo(): array
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cicloActivo = Ciclo::factory()->activo()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $cicloActivo->id]);
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $cicloActivo->id,
        ]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        return [$usuario, $estudiante, $horario];
    }

    public function test_una_sola_cuota_vencida_del_ciclo_actual_bloquea_ver_las_notas_aunque_no_llegue_al_umbral_general(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteConUnaCuotaVencidaEnCicloActivo();

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->assertFalse($this->app->make(BloqueoAccesoService::class)->estaBloqueado($estudiante));

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('no están disponibles')
            ->assertDontSee('18.00');
    }

    public function test_una_sola_cuota_vencida_del_ciclo_actual_bloquea_generar_la_libreta(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteConUnaCuotaVencidaEnCicloActivo();

        $this->actingAs($usuario)
            ->get(route('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $horario->ciclo]))
            ->assertOk()
            ->assertSee('libreta no está disponible');
    }

    public function test_un_estudiante_bloqueado_no_ve_el_enlace_externo_de_una_evaluacion(): void
    {
        [$usuario, , $horario] = $this->estudianteBloqueado();

        // Publicada a propósito: así el "no lo ve" se debe únicamente al
        // bloqueo por deuda, sin mezclarlo con el nuevo requisito de que
        // la evaluación esté publicada.
        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'), 'https://forms.test/examen');
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario);
        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertDontSee('Evaluaciones para rendir')
            ->assertDontSee('https://forms.test/examen');
    }

    public function test_una_sola_cuota_vencida_del_ciclo_actual_bloquea_ver_el_enlace_externo(): void
    {
        [$usuario, , $horario] = $this->estudianteConUnaCuotaVencidaEnCicloActivo();

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'), 'https://forms.test/examen');
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario);
        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertDontSee('Evaluaciones para rendir')
            ->assertDontSee('https://forms.test/examen');
    }

    public function test_una_cuota_vencida_de_un_ciclo_distinto_al_actual_no_bloquea_las_notas(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cicloActivo = Ciclo::factory()->activo()->create();
        $cicloNoActivo = Ciclo::factory()->create();

        $horario = Horario::factory()->create(['ciclo_id' => $cicloActivo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $cicloActivo->id,
        ]);

        $matriculaAnterior = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $cicloNoActivo->id,
        ]);
        $planAnterior = PlanPago::factory()->create(['matricula_id' => $matriculaAnterior->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $planAnterior->id, 'numero' => 1]);

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('18.00')
            ->assertDontSee('no están disponibles');
    }
}
