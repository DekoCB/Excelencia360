<?php

namespace Tests\Feature\Reportes;

use App\Modules\Academico\Models\Horario;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Reportes\Services\HistorialEstudianteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialEstudianteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HistorialEstudianteService
    {
        return $this->app->make(HistorialEstudianteService::class);
    }

    public function test_dni_inexistente_devuelve_null(): void
    {
        $this->assertNull($this->service()->porDni('00000000'));
    }

    public function test_las_matriculas_quedan_en_orden_cronologico(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '11111111']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()->subMonths(6)]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()->subYear()]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);

        $historial = $this->service()->porDni('11111111');

        $this->assertCount(3, $historial['matriculas']);
        $fechas = $historial['matriculas']->pluck('fecha_matricula')->map(fn ($fecha) => $fecha->format('Y-m-d'))->all();
        $this->assertSame($fechas, collect($fechas)->sort()->values()->all());
    }

    public function test_el_resumen_de_pagos_suma_a_traves_de_varias_matriculas(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '22222222']);

        $matriculaUno = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $planUno = PlanPago::factory()->create(['matricula_id' => $matriculaUno->id]);
        $cuotaPagadaUno = Cuota::factory()->pagada()->create(['plan_pago_id' => $planUno->id, 'numero' => 1, 'monto' => 100]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuotaPagadaUno->id, 'monto' => 100]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $planUno->id, 'numero' => 2, 'monto' => 150]);

        $matriculaDos = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $planDos = PlanPago::factory()->create(['matricula_id' => $matriculaDos->id]);
        $cuotaPagadaDos = Cuota::factory()->pagada()->create(['plan_pago_id' => $planDos->id, 'numero' => 1, 'monto' => 200]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuotaPagadaDos->id, 'monto' => 200]);
        Cuota::factory()->create(['plan_pago_id' => $planDos->id, 'numero' => 2, 'monto' => 50, 'estado' => EstadoCuotaEnum::EXONERADO]);

        $historial = $this->service()->porDni('22222222');

        $this->assertSame(300.0, $historial['resumenPagos']['totalPagado']);
        $this->assertSame(50.0, $historial['resumenPagos']['totalExonerado']);
        $this->assertCount(1, $historial['resumenPagos']['cuotasVencidas']);
        $this->assertSame(150.0, (float) $historial['resumenPagos']['cuotasVencidas']->first()->monto);
    }

    /**
     * Pedido del cliente: además de las cuotas ya vencidas, ver las que
     * todavía están pendientes con su fecha de vencimiento -- sin
     * duplicar las vencidas en ambas listas.
     */
    public function test_el_resumen_de_pagos_incluye_las_cuotas_pendientes_por_vencer_sin_duplicar_las_vencidas(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '99998888']);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        $porVencer = Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1, 'monto' => 100]);
        $vencida = Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2, 'monto' => 150]);

        $historial = $this->service()->porDni('99998888');

        $this->assertCount(1, $historial['resumenPagos']['cuotasPendientes']);
        $this->assertSame($porVencer->id, $historial['resumenPagos']['cuotasPendientes']->first()->id);
        $this->assertCount(1, $historial['resumenPagos']['cuotasVencidas']);
        $this->assertSame($vencida->id, $historial['resumenPagos']['cuotasVencidas']->first()->id);
    }

    /**
     * Regresión: el cliente reportó que "Pagado" no reflejaba los pagos
     * parciales, y que "Pendiente" mostraba el monto completo de la cuota
     * en vez de lo que realmente faltaba. resumenPagos() ahora suma
     * montoPagado()/saldoPendiente() de cada cuota en vez del monto
     * nominal según su estado -- ver HistorialEstudianteService.
     */
    public function test_una_cuota_con_pago_parcial_aprobado_se_refleja_en_pagado_y_en_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '44444444']);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1, 'monto' => 80]);

        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuota->id, 'monto' => 40]);

        $historial = $this->service()->porDni('44444444');

        $this->assertSame(40.0, $historial['resumenPagos']['totalPagado']);
        $this->assertSame(40.0, $historial['resumenPagos']['totalPendiente']);
    }

    /**
     * Mismo requisito que la regresión de cuotas de arriba, ahora para
     * cargos adicionales (Convalidación, Exoneración...): un pago parcial
     * aprobado contra un cargo debe sumar/restar igual de bien, no dejar
     * huecos -- pedido explícito del cliente al aprobar esta funcionalidad.
     */
    public function test_un_cargo_adicional_con_pago_parcial_aprobado_se_refleja_en_pagado_y_en_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '77778888']);
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'concepto' => 'Convalidación', 'monto' => 80]);

        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cargo_adicional_id' => $cargo->id, 'monto' => 40]);

        $historial = $this->service()->porDni('77778888');

        $this->assertSame(40.0, $historial['resumenPagos']['totalPagado']);
        $this->assertSame(40.0, $historial['resumenPagos']['totalPendiente']);
        $this->assertCount(1, $historial['resumenPagos']['cargosAdicionalesPendientes']);
        $this->assertSame(40.0, $historial['resumenPagos']['cargosAdicionalesPendientes']->first()->saldoPendiente());
    }

    public function test_pagos_trae_el_detalle_de_cada_pago_sin_importar_su_estado(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '55556666']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'monto' => 100]);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => EstadoPagoEnum::PENDIENTE, 'monto' => 50]);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => EstadoPagoEnum::RECHAZADO, 'monto' => 30]);

        $historial = $this->service()->porDni('55556666');

        $this->assertCount(3, $historial['pagos']);
    }

    public function test_los_documentos_de_las_3_fuentes_aparecen_separados(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '33333333']);
        DocumentoEstudiante::factory()->create(['estudiante_id' => $estudiante->id]);
        Certificado::factory()->create(['estudiante_id' => $estudiante->id]);
        Libreta::factory()->create(['estudiante_id' => $estudiante->id]);

        $historial = $this->service()->porDni('33333333');

        $this->assertCount(1, $historial['documentosSubidos']);
        $this->assertCount(1, $historial['documentosEmitidos']);
        $this->assertCount(1, $historial['libretas']);
    }

    public function test_las_notas_por_ciclo_solo_cuentan_matriculas_aprobadas(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '44444444']);

        $matriculaAprobada = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada']);
        $horario = Horario::factory()->create(['grado_id' => $matriculaAprobada->grado_id, 'ciclo_id' => $matriculaAprobada->ciclo_id]);
        $evaluacion = Evaluacion::factory()->create(['horario_id' => $horario->id]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id, 'nota_numerica' => 16]);
        $evaluacion->update(['estado' => 'publicada']);

        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'pendiente']);

        $historial = $this->service()->porDni('44444444');

        $this->assertCount(1, $historial['notasPorCiclo']);
        $this->assertSame($matriculaAprobada->ciclo_id, $historial['notasPorCiclo']->first()['ciclo']->id);
    }
}
