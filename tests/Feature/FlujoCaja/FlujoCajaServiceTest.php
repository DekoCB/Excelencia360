<?php

namespace Tests\Feature\FlujoCaja;

use App\Models\User;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\FlujoCaja\Services\FlujoCajaService;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlujoCajaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FlujoCajaService
    {
        return $this->app->make(FlujoCajaService::class);
    }

    public function test_registrar_egreso_crea_el_registro_y_adjunta_el_comprobante(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();

        $egreso = $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::SERVICIOS,
            'descripcion' => 'Recibo de luz de setiembre',
            'monto' => 150.5,
            'metodo' => MetodoPagoEnum::TRANSFERENCIA,
            'fecha_egreso' => '2026-09-05',
        ], UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'), $usuario->id);

        $this->assertDatabaseHas('egresos', [
            'id' => $egreso->id,
            'categoria' => 'servicios',
            'monto' => '150.50',
            'metodo' => 'transferencia',
            'registrado_por' => $usuario->id,
        ]);
        $this->assertNotNull($egreso->getFirstMedia('comprobante'));
    }

    public function test_registrar_egreso_sin_comprobante_no_falla(): void
    {
        $egreso = $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => null,
            'monto' => 30,
            'metodo' => MetodoPagoEnum::EFECTIVO,
            'fecha_egreso' => now()->format('Y-m-d'),
        ], null, null);

        $this->assertDatabaseHas('egresos', ['id' => $egreso->id]);
        $this->assertNull($egreso->getFirstMedia('comprobante'));
    }

    public function test_ingresos_del_periodo_suma_solo_pagos_aprobados_en_el_rango(): void
    {
        Pago::factory()->aprobado()->create(['monto' => 100, 'fecha_aprobacion' => '2026-09-10']);
        Pago::factory()->aprobado()->create(['monto' => 50, 'fecha_aprobacion' => '2026-08-15']);
        // No debería contar: sigue pendiente.
        Pago::factory()->create(['estado' => EstadoPagoEnum::PENDIENTE, 'monto' => 999]);

        $total = $this->service()->ingresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(100.0, $total);
    }

    public function test_egresos_del_periodo_suma_los_egresos_del_rango(): void
    {
        Egreso::factory()->create(['monto' => 40, 'fecha_egreso' => '2026-09-05']);
        Egreso::factory()->create(['monto' => 60, 'fecha_egreso' => '2026-09-20']);
        Egreso::factory()->create(['monto' => 999, 'fecha_egreso' => '2026-08-01']);

        $total = $this->service()->egresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(100.0, $total);
    }

    public function test_movimientos_del_periodo_mezcla_ingresos_y_egresos_ordenados_por_fecha(): void
    {
        Pago::factory()->aprobado()->create(['monto' => 100, 'fecha_aprobacion' => '2026-09-10']);
        Egreso::factory()->create(['monto' => 40, 'fecha_egreso' => '2026-09-20']);

        $movimientos = $this->service()->movimientosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertCount(2, $movimientos);
        $this->assertSame('egreso', $movimientos->first()['tipo']);
        $this->assertSame('ingreso', $movimientos->last()['tipo']);
    }
}
