<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class PagoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PagoService
    {
        return $this->app->make(PagoService::class);
    }

    private function aprobador(): int
    {
        return User::factory()->create()->id;
    }

    public function test_registrar_un_pago_queda_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'yape']], null, null, null);

        $this->assertSame(EstadoPagoEnum::PENDIENTE, $pago->estado);
        $this->assertSame(MetodoPagoEnum::YAPE, $pago->metodo);
        $this->assertSame('100.00', $pago->monto);
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_la_misma_cuota(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'yape']], $cuota, null, null);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'transferencia']], $cuota, null, null);
    }

    public function test_no_permite_registrar_un_pago_sin_ninguna_parte(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->registrar($estudiante, $concepto, [], null, null, null);
    }

    public function test_registrar_un_pago_en_varias_partes_suma_el_monto_total_y_marca_metodo_mixto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'yape'],
        ], null, null, null);

        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::MIXTO, $pago->metodo);
        $this->assertCount(2, $pago->partes);
        $this->assertSame('60.00', $pago->partes->firstWhere('metodo', MetodoPagoEnum::EFECTIVO)->monto);
        $this->assertSame('40.00', $pago->partes->firstWhere('metodo', MetodoPagoEnum::YAPE)->monto);
    }

    public function test_registrar_un_pago_con_varias_partes_del_mismo_metodo_no_queda_mixto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'efectivo'],
        ], null, null, null);

        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::EFECTIVO, $pago->metodo);
        $this->assertCount(2, $pago->partes);
    }

    public function test_aprobar_un_pago_marca_la_cuota_como_pagada_y_genera_recibo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => (float) $cuota->monto, 'metodo' => 'yape']], $cuota, null, null);

        $aprobado = $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->assertSame(EstadoPagoEnum::APROBADO, $aprobado->estado);
        $this->assertSame('pagado', $cuota->fresh()->estado->value);
        $this->assertNotNull($aprobado->recibo);
        $this->assertSame(SerieReciboEnum::ORIGINAL, $aprobado->recibo->serie);
        $this->assertNotNull($aprobado->recibo->getFirstMedia('pdf'));
    }

    public function test_un_pago_parcial_no_marca_la_cuota_como_pagada_y_el_saldo_se_va_descontando(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create(['monto' => 80]);

        $primerPago = $this->service()->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'yape']], $cuota, null, null);
        $this->service()->aprobar($primerPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cuota->refresh();
        $this->assertSame('pendiente', $cuota->estado->value);
        $this->assertSame(40.0, $cuota->saldoPendiente());

        $segundoPago = $this->service()->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'efectivo']], $cuota, null, null);
        $this->service()->aprobar($segundoPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cuota->refresh();
        $this->assertSame('pagado', $cuota->estado->value);
        $this->assertSame(0.0, $cuota->saldoPendiente());
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_el_mismo_cargo_adicional(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create();

        $this->service()->registrar($estudiante, $concepto, [['monto' => 50.0, 'metodo' => 'yape']], null, null, null, cargoAdicional: $cargo);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, $concepto, [['monto' => 50.0, 'metodo' => 'transferencia']], null, null, null, cargoAdicional: $cargo);
    }

    public function test_un_pago_parcial_no_marca_el_cargo_adicional_como_pagado_y_el_saldo_se_va_descontando(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['monto' => 80]);

        $primerPago = $this->service()->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'yape']], null, null, null, cargoAdicional: $cargo);
        $this->service()->aprobar($primerPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pendiente', $cargo->estado->value);
        $this->assertSame(40.0, $cargo->saldoPendiente());

        $segundoPago = $this->service()->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'efectivo']], null, null, null, cargoAdicional: $cargo);
        $this->service()->aprobar($segundoPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pagado', $cargo->estado->value);
        $this->assertSame(0.0, $cargo->saldoPendiente());
    }

    public function test_editar_monto_de_cargo_adicional_actualiza_el_monto(): void
    {
        $cargo = CargoAdicional::factory()->create(['monto' => 50]);

        $actualizado = $this->service()->editarMontoCargoAdicional($cargo, 80.0);

        $this->assertSame('80.00', $actualizado->monto);
    }

    public function test_editar_monto_de_cargo_adicional_a_cero_o_negativo_lanza_excepcion(): void
    {
        $cargo = CargoAdicional::factory()->create(['monto' => 50]);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoCargoAdicional($cargo, 0.0);
    }

    public function test_editar_monto_de_cargo_adicional_menor_a_lo_ya_pagado_lanza_excepcion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 80]);

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'yape']], null, null, null, cargoAdicional: $cargo);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoCargoAdicional($cargo, 30.0);
    }

    /**
     * Subir el monto de un cargo ya pagado del todo debe reabrirlo: el
     * estado siempre tiene que reflejar el saldo real, no quedarse en
     * "pagado" con un saldo pendiente mayor a cero -- mismo requisito de
     * "sin huecos" que el resto de la funcionalidad.
     */
    public function test_editar_monto_de_cargo_adicional_ya_pagado_lo_vuelve_a_pendiente_si_deja_saldo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 50]);

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 50.0, 'metodo' => 'yape']], null, null, null, cargoAdicional: $cargo);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pagado', $cargo->estado->value);

        $actualizado = $this->service()->editarMontoCargoAdicional($cargo, 70.0);

        $this->assertSame('pendiente', $actualizado->estado->value);
        $this->assertSame(20.0, $actualizado->saldoPendiente());
    }

    public function test_nombre_concepto_usa_el_concepto_del_cargo_adicional_cuando_corresponde(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Otro']);
        $cargo = CargoAdicional::factory()->create(['concepto' => 'Convalidación']);

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 50.0, 'metodo' => 'yape']], null, null, null, cargoAdicional: $cargo);

        $this->assertSame('Convalidación', $pago->nombreConcepto());
    }

    public function test_registrar_guarda_la_nota_de_una_parte_cuando_se_indica(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [
            ['monto' => 100.0, 'metodo' => 'yape', 'nota' => 'Director'],
        ], null, null, null);

        $this->assertSame('Director', $pago->partes->first()->nota);
        $this->assertSame('Yape Director', $pago->partes->first()->metodoConNota());
        $this->assertSame('Yape Director', $pago->medioPagoResumen());
    }

    public function test_registrar_sin_nota_la_deja_en_null_y_el_metodo_se_muestra_solo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'yape']], null, null, null);

        $this->assertNull($pago->partes->first()->nota);
        $this->assertSame('Yape', $pago->partes->first()->metodoConNota());
    }

    /**
     * Pedido del cliente: la fecha de pago (a diferencia de la fecha de
     * emisión del recibo, que siempre es la de hoy y no se toca) debe
     * poder editarse, ej. para un cobro en efectivo recibido unos días
     * antes de que recién se registre en el sistema.
     */
    public function test_registrar_con_fecha_de_pago_explicita_la_guarda_en_vez_de_hoy(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar(
            $estudiante,
            $concepto,
            [['monto' => 100.0, 'metodo' => 'efectivo']],
            null,
            null,
            null,
            null,
            null,
            '2026-09-01',
        );

        $this->assertSame('2026-09-01', $pago->fecha_pago->format('Y-m-d'));
    }

    public function test_registrar_sin_fecha_de_pago_usa_hoy(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $this->assertSame(now()->format('Y-m-d'), $pago->fecha_pago->format('Y-m-d'));
    }

    public function test_aprobar_respeta_la_serie_elegida_a_mano(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $aprobado = $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::COPIA);

        $this->assertSame(SerieReciboEnum::COPIA, $aprobado->recibo->serie);
    }

    public function test_rechazar_un_pago_registra_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $rechazado = $this->service()->rechazar($pago, $this->aprobador(), 'Comprobante ilegible');

        $this->assertSame(EstadoPagoEnum::RECHAZADO, $rechazado->estado);
        $this->assertSame('Comprobante ilegible', $rechazado->motivo_rechazo);
    }

    public function test_no_permite_aprobar_un_pago_ya_procesado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->expectException(ValidationException::class);

        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);
    }

    public function test_pendientes_de_aprobacion_solo_incluye_pagos_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pendiente = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $aprobado = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $this->service()->aprobar($aprobado, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cola = $this->service()->pendientesDeAprobacion();

        $this->assertTrue($cola->contains('id', $pendiente->id));
        $this->assertFalse($cola->contains('id', $aprobado->id));
    }
}
