<?php

namespace Tests\Feature\Pagos;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BloqueoAccesoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BloqueoAccesoService
    {
        return $this->app->make(BloqueoAccesoService::class);
    }

    private function planDe(Estudiante $estudiante): PlanPago
    {
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        return PlanPago::factory()->create(['matricula_id' => $matricula->id]);
    }

    private function planEnCiclo(Estudiante $estudiante, Ciclo $ciclo): PlanPago
    {
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);

        return PlanPago::factory()->create(['matricula_id' => $matricula->id]);
    }

    public function test_no_esta_bloqueado_sin_cuotas_vencidas(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->assertFalse($this->service()->estaBloqueado($estudiante));
    }

    public function test_se_bloquea_con_dos_o_mas_cuotas_vencidas(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->service()->evaluarYDesbloquear($estudiante);

        $this->assertTrue($this->service()->estaBloqueado($estudiante));
        $this->assertDatabaseHas('bloqueos_acceso', ['estudiante_id' => $estudiante->id, 'activo' => 1]);
    }

    public function test_no_se_bloquea_con_una_sola_cuota_vencida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->service()->evaluarYDesbloquear($estudiante);

        $this->assertFalse($this->service()->estaBloqueado($estudiante));
    }

    public function test_se_desbloquea_cuando_bajan_las_cuotas_vencidas(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        $cuotaUno = Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->service()->evaluarYDesbloquear($estudiante);
        $this->assertTrue($this->service()->estaBloqueado($estudiante));

        $cuotaUno->update(['estado' => 'pagado']);
        $this->service()->evaluarYDesbloquear($estudiante);

        $this->assertFalse($this->service()->estaBloqueado($estudiante));
        $this->assertDatabaseHas('bloqueos_acceso', ['estudiante_id' => $estudiante->id, 'activo' => 0]);
    }

    public function test_cuotas_exoneradas_no_cuentan_como_vencidas(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1, 'estado' => 'exonerado']);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2, 'estado' => 'exonerado']);

        $this->assertCount(0, $this->service()->cuotasVencidasDe($estudiante));
    }

    public function test_una_sola_cuota_vencida_del_ciclo_actual_ya_activa_la_regla_estricta(): void
    {
        $estudiante = Estudiante::factory()->create();
        $cicloActivo = Ciclo::factory()->activo()->create();
        $plan = $this->planEnCiclo($estudiante, $cicloActivo);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        // El bloqueo general no se activa (umbral 2), pero la regla estricta sí.
        $this->assertFalse($this->service()->estaBloqueado($estudiante));
        $this->assertTrue($this->service()->tieneCuotasVencidasEnCicloActual($estudiante));
    }

    public function test_sin_cuotas_vencidas_la_regla_estricta_no_se_activa(): void
    {
        $estudiante = Estudiante::factory()->create();
        $cicloActivo = Ciclo::factory()->activo()->create();
        $plan = $this->planEnCiclo($estudiante, $cicloActivo);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->assertFalse($this->service()->tieneCuotasVencidasEnCicloActual($estudiante));
    }

    public function test_una_cuota_vencida_de_un_ciclo_que_no_es_el_actual_no_activa_la_regla_estricta(): void
    {
        $estudiante = Estudiante::factory()->create();
        $cicloNoActivo = Ciclo::factory()->create();
        $plan = $this->planEnCiclo($estudiante, $cicloNoActivo);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        Ciclo::factory()->activo()->create();

        $this->assertFalse($this->service()->tieneCuotasVencidasEnCicloActual($estudiante));
    }

    public function test_sin_ningun_ciclo_activo_la_regla_estricta_no_se_activa(): void
    {
        $estudiante = Estudiante::factory()->create();
        $plan = $this->planDe($estudiante);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->assertFalse($this->service()->tieneCuotasVencidasEnCicloActual($estudiante));
    }
}
