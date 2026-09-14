<?php

namespace Tests\Feature\Pagos;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanPagoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlanPagoService
    {
        return $this->app->make(PlanPagoService::class);
    }

    public function test_crear_un_plan_genera_las_cuotas_indicadas(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);

        $this->assertSame(6, $plan->cuotas()->count());
        $this->assertEquals(600.0, (float) $plan->cuotas()->sum('monto'));
    }

    public function test_la_ultima_cuota_absorbe_el_redondeo(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::OCHO, 100.0);

        $cuotas = $plan->cuotas()->orderBy('numero')->get();

        $this->assertEquals(100.0, (float) $cuotas->sum('monto'));
        $this->assertEqualsWithDelta(12.5, (float) $cuotas->first()->monto, 0.01);
    }

    public function test_las_fechas_de_vencimiento_son_mensuales_desde_la_matricula(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2026-03-01']);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);

        $cuotas = $plan->cuotas()->orderBy('numero')->get();

        $this->assertSame('2026-04-01', $cuotas[0]->fecha_vencimiento->format('Y-m-d'));
        $this->assertSame('2026-09-01', $cuotas[5]->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_crear_con_cuotas_personalizadas_usa_los_montos_y_fechas_dados(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 350.0, [
            ['monto' => 350.0, 'fecha_vencimiento' => '2026-05-15'],
        ]);

        $cuota = $plan->cuotas()->firstOrFail();
        $this->assertSame(1, $cuota->numero);
        $this->assertSame('350.00', $cuota->monto);
        $this->assertSame('2026-05-15', $cuota->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_no_permite_crear_dos_planes_para_la_misma_matricula(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 100.0);

        $this->expectException(ValidationException::class);

        $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);
    }

    public function test_editar_monto_total_reparte_la_diferencia_entre_las_cuotas_pendientes(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::CUATRO, 400.0);
        $cuotas = $plan->cuotas()->orderBy('numero')->get();
        $cuotas[0]->update(['estado' => EstadoCuotaEnum::PAGADO]);

        $plan = $this->service()->editarMontoTotal($plan, 700.0);

        $this->assertSame('700.00', $plan->monto_total);
        // Las 3 cuotas pendientes (600 = 700 - 100 ya pagada) se reparten en partes iguales.
        $pendientes = $plan->cuotas->where('estado', EstadoCuotaEnum::PENDIENTE)->sortBy('numero')->values();
        $this->assertEquals(200.0, (float) $pendientes[0]->monto);
        $this->assertEquals(200.0, (float) $pendientes[1]->monto);
        $this->assertEquals(200.0, (float) $pendientes[2]->monto);
    }

    public function test_editar_monto_total_no_toca_cuotas_pagadas_ni_exoneradas(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::CUATRO, 400.0);
        $cuotas = $plan->cuotas()->orderBy('numero')->get();
        $cuotas[0]->update(['estado' => EstadoCuotaEnum::PAGADO]);
        $cuotas[1]->update(['estado' => EstadoCuotaEnum::EXONERADO]);

        $this->service()->editarMontoTotal($plan, 500.0);

        $this->assertSame('100.00', $cuotas[0]->fresh()->monto);
        $this->assertSame('100.00', $cuotas[1]->fresh()->monto);
    }

    public function test_editar_monto_total_la_ultima_cuota_pendiente_absorbe_el_redondeo(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::CUATRO, 400.0);

        $plan = $this->service()->editarMontoTotal($plan, 100.0);

        $pendientes = $plan->cuotas->sortBy('numero')->values();
        $this->assertEquals(100.0, (float) $pendientes->sum('monto'));
        $this->assertEqualsWithDelta(25.0, (float) $pendientes[0]->monto, 0.01);
    }

    public function test_editar_monto_total_menor_a_lo_ya_pagado_lanza_excepcion(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::CUATRO, 400.0);
        $plan->cuotas()->first()->update(['estado' => EstadoCuotaEnum::PAGADO]);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoTotal($plan, 50.0);
    }

    public function test_editar_monto_total_sin_cuotas_pendientes_lanza_excepcion(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 100.0);
        $plan->cuotas()->update(['estado' => EstadoCuotaEnum::PAGADO]);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoTotal($plan, 200.0);
    }

    public function test_editar_monto_total_cero_o_negativo_lanza_excepcion(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 100.0);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoTotal($plan, 0.0);
    }
}
