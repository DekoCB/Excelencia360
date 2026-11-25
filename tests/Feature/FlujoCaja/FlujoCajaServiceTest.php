<?php

namespace Tests\Feature\FlujoCaja;

use App\Models\User;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\FlujoCaja\Services\FlujoCajaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function test_ingresos_y_egresos_del_periodo_se_pueden_filtrar_por_usuario(): void
    {
        $cajero = User::factory()->create();
        $otro = User::factory()->create();

        Pago::factory()->aprobado()->create(['monto' => 100, 'fecha_aprobacion' => '2026-09-10', 'registrado_por' => $cajero->id]);
        Pago::factory()->aprobado()->create(['monto' => 999, 'fecha_aprobacion' => '2026-09-10', 'registrado_por' => $otro->id]);
        Egreso::factory()->create(['monto' => 40, 'fecha_egreso' => '2026-09-05', 'registrado_por' => $cajero->id]);
        Egreso::factory()->create(['monto' => 999, 'fecha_egreso' => '2026-09-05', 'registrado_por' => $otro->id]);

        $inicio = Carbon::parse('2026-09-01');
        $fin = Carbon::parse('2026-09-30');

        $this->assertSame(100.0, $this->service()->ingresosDelPeriodo($inicio, $fin, $cajero->id));
        $this->assertSame(40.0, $this->service()->egresosDelPeriodo($inicio, $fin, $cajero->id));
        $this->assertCount(2, $this->service()->movimientosDelPeriodo($inicio, $fin, $cajero->id));
    }

    public function test_abrir_turno_crea_el_registro_y_rechaza_un_segundo_turno_abierto(): void
    {
        $cajero = User::factory()->create();

        $turno = $this->service()->abrirTurno($cajero, 100.0);

        $this->assertDatabaseHas('turnos_caja', [
            'id' => $turno->id,
            'usuario_id' => $cajero->id,
            'monto_inicial' => '100.00',
            'estado' => 'abierto',
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->abrirTurno($cajero, 50.0);
    }

    public function test_turno_abierto_de_devuelve_null_si_no_tiene_uno(): void
    {
        $cajero = User::factory()->create();

        $this->assertNull($this->service()->turnoAbiertoDe($cajero));

        $turno = $this->service()->abrirTurno($cajero, 100.0);

        $this->assertTrue($this->service()->turnoAbiertoDe($cajero)->is($turno));
    }

    public function test_registrar_egreso_y_registrar_pago_quedan_ligados_al_turno_abierto(): void
    {
        $cajero = User::factory()->create();
        $turno = $this->service()->abrirTurno($cajero, 100.0);

        $egreso = $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => null,
            'monto' => 30,
            'metodo' => MetodoPagoEnum::EFECTIVO,
            'fecha_egreso' => now()->format('Y-m-d'),
        ], null, $cajero->id);

        $this->assertSame($turno->id, $egreso->turno_caja_id);

        $concepto = ConceptoPago::factory()->mensualidad()->create();
        $estudiante = Estudiante::factory()->create();

        $pago = $this->app->make(PagoService::class)->registrar(
            $estudiante,
            $concepto,
            [['monto' => 50.0, 'metodo' => 'efectivo']],
            null,
            null,
            $cajero->id,
        );

        $this->assertSame($turno->id, $pago->turno_caja_id);
    }

    public function test_cerrar_turno_calcula_el_monto_esperado_y_guarda_lo_declarado(): void
    {
        $cajero = User::factory()->create();
        $turno = $this->service()->abrirTurno($cajero, 100.0);

        Pago::factory()->aprobado()->create(['monto' => 50, 'registrado_por' => $cajero->id, 'turno_caja_id' => $turno->id]);
        Egreso::factory()->create(['monto' => 20, 'registrado_por' => $cajero->id, 'turno_caja_id' => $turno->id]);

        $this->assertSame(130.0, $this->service()->montoCalculadoDelTurno($turno));

        $cerrado = $this->service()->cerrarTurno($turno, 125.0);

        $this->assertSame('cerrado', $cerrado->estado);
        $this->assertSame(125.0, (float) $cerrado->monto_final_declarado);
        $this->assertNotNull($cerrado->hora_cierre);
    }

    public function test_movimientos_del_turno_solo_trae_los_de_ese_turno(): void
    {
        $cajero = User::factory()->create();
        $turno = $this->service()->abrirTurno($cajero, 100.0);

        Pago::factory()->aprobado()->create(['monto' => 50, 'turno_caja_id' => $turno->id]);
        Pago::factory()->aprobado()->create(['monto' => 999]);

        $movimientos = $this->service()->movimientosDelTurno($turno);

        $this->assertCount(1, $movimientos);
        $this->assertSame(50.0, $movimientos->first()['monto']);
    }
}
