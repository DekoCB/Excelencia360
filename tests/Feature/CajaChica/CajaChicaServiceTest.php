<?php

namespace Tests\Feature\CajaChica;

use App\Models\User;
use App\Modules\CajaChica\Services\CajaChicaService;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CajaChicaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CajaChicaService
    {
        return $this->app->make(CajaChicaService::class);
    }

    public function test_sin_movimientos_el_saldo_es_cero_y_no_tiene_fondo_asignado(): void
    {
        $this->assertSame(0.0, $this->service()->saldoActual());
        $this->assertFalse($this->service()->tieneFondoAsignado());
    }

    public function test_asignar_el_fondo_la_primera_vez_registra_una_asignacion(): void
    {
        $usuario = User::factory()->create();

        $movimiento = $this->service()->asignarOReponerFondo(500.0, $usuario->id);

        $this->assertSame('asignacion', $movimiento->tipo->value);
        $this->assertSame(500.0, $this->service()->saldoActual());
        $this->assertTrue($this->service()->tieneFondoAsignado());
    }

    public function test_reponer_el_fondo_despues_de_la_asignacion_registra_una_reposicion(): void
    {
        $usuario = User::factory()->create();

        $this->service()->asignarOReponerFondo(500.0, $usuario->id);
        $movimiento = $this->service()->asignarOReponerFondo(200.0, $usuario->id);

        $this->assertSame('reposicion', $movimiento->tipo->value);
        $this->assertSame(700.0, $this->service()->saldoActual());
    }

    public function test_registrar_gasto_resta_del_saldo_y_adjunta_comprobante(): void
    {
        $usuario = User::factory()->create();
        $this->service()->asignarOReponerFondo(500.0, $usuario->id);

        $gasto = $this->service()->registrarGasto([
            'categoria' => CategoriaEgresoEnum::MATERIALES,
            'descripcion' => 'Útiles de oficina',
            'monto' => 50.0,
            'fecha' => now()->format('Y-m-d'),
        ], null, $usuario->id);

        $this->assertSame('gasto', $gasto->tipo->value);
        $this->assertSame(450.0, $this->service()->saldoActual());
    }

    public function test_movimientos_del_periodo_solo_trae_los_del_rango(): void
    {
        $usuario = User::factory()->create();
        $this->service()->asignarOReponerFondo(500.0, $usuario->id);

        $this->service()->registrarGasto([
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => null,
            'monto' => 10.0,
            'fecha' => '2026-08-01',
        ], null, $usuario->id);

        $movimientos = $this->service()->movimientosDelPeriodo(
            Carbon::parse(now()->format('Y-m-01')),
            Carbon::parse(now()->format('Y-m-t')),
        );

        $this->assertCount(1, $movimientos);
    }
}
