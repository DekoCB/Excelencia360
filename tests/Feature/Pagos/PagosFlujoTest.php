<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Modules\Pagos\Services\SolicitudCambioMontoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PagosFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrativo_registra_un_pago_y_tesoreria_lo_aprueba(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '150')
            ->set('partes.0.metodo', 'yape')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'concepto_id' => $concepto->id,
            'estado' => 'pendiente',
        ]);

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();

        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $this->actingAs($tesoreria);

        Volt::test('pagos.index')
            ->call('aprobar', $pago->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'estado' => 'aprobado']);
        $this->assertDatabaseHas('recibos', ['pago_id' => $pago->id]);
    }

    public function test_registrar_un_pago_con_concepto_otro_exige_y_guarda_el_detalle_libre(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['tipo' => 'otro']);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '25')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasErrors('detalle');

        $this->assertDatabaseMissing('pagos', ['estudiante_id' => $estudiante->id]);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('detalle', 'Duplicado de constancia de matrícula')
            ->set('partes.0.monto', '25')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'detalle' => 'Duplicado de constancia de matrícula',
        ]);
    }

    public function test_registrar_un_pago_en_varias_partes_con_distinto_metodo_queda_como_un_solo_registro(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '60')
            ->set('partes.0.metodo', 'efectivo')
            ->call('agregarParte')
            ->set('partes.1.monto', '40')
            ->set('partes.1.metodo', 'yape')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('pagos', 1);
        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::MIXTO, $pago->metodo);
        $this->assertDatabaseCount('pago_partes', 2);
        $this->assertDatabaseHas('pago_partes', ['pago_id' => $pago->id, 'monto' => 60, 'metodo' => 'efectivo']);
        $this->assertDatabaseHas('pago_partes', ['pago_id' => $pago->id, 'monto' => 40, 'metodo' => 'yape']);
    }

    public function test_quitar_una_parte_del_formulario_de_registrar_pago(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '60')
            ->set('partes.0.metodo', 'efectivo')
            ->call('agregarParte')
            ->set('partes.1.monto', '40')
            ->set('partes.1.metodo', 'yape')
            ->call('quitarParte', 1)
            ->call('registrarPago')
            ->assertHasNoErrors();

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame('60.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::EFECTIVO, $pago->metodo);
        $this->assertDatabaseCount('pago_partes', 1);
    }

    public function test_tesoreria_rechaza_un_pago_con_motivo(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->app->make(PagoService::class)
            ->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $this->actingAs($tesoreria);

        Volt::test('pagos.index')
            ->set("motivoRechazo.{$pago->id}", 'Comprobante no corresponde')
            ->call('rechazar', $pago->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'id' => $pago->id,
            'estado' => 'rechazado',
            'motivo_rechazo' => 'Comprobante no corresponde',
        ]);
    }

    public function test_un_administrativo_no_puede_aprobar_pagos(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->app->make(PagoService::class)
            ->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $this->actingAs($administrativo);

        rescue(fn () => Volt::test('pagos.index')->call('aprobar', $pago->id), report: false);

        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'estado' => 'pendiente']);
    }

    public function test_editar_el_monto_de_un_concepto_queda_pendiente_de_aprobacion(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Mensualidad', 'monto_base' => 100]);

        $this->actingAs($coordinador);

        Volt::test('pagos.conceptos')
            ->call('abrirModal', $concepto->id)
            ->set('montoBase', '150')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('100.00', $concepto->fresh()->monto_base);
        $this->assertDatabaseHas('solicitudes_cambio_monto', [
            'concepto_pago_id' => $concepto->id,
            'monto_propuesto' => 150,
            'estado' => 'pendiente',
        ]);
    }

    public function test_direccion_aprueba_un_cambio_de_monto_desde_la_vista_de_conceptos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $solicitud = $this->app->make(SolicitudCambioMontoService::class)->solicitar($concepto, 150.0, $coordinador->id);

        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        $this->actingAs($direccion);

        Volt::test('pagos.conceptos')
            ->call('aprobarCambioMonto', $solicitud->id)
            ->assertHasNoErrors();

        $this->assertSame('150.00', $concepto->fresh()->monto_base);
    }

    public function test_coordinador_crea_un_plan_de_pago_desde_el_listado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $this->actingAs($coordinador);

        Volt::test('pagos.index')
            ->set("numeroCuotasPorMatricula.{$matricula->id}", (string) NumeroCuotasEnum::SEIS->value)
            ->set("montoTotalPorMatricula.{$matricula->id}", '600')
            ->call('crearPlan', $matricula->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('planes_pago', ['matricula_id' => $matricula->id, 'numero_cuotas' => 6]);
        $this->assertDatabaseCount('cuotas', 6);
    }

    public function test_el_estudiante_sube_un_comprobante_para_su_cuota(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $plan = $this->app->make(PlanPagoService::class)->crear($matricula, NumeroCuotasEnum::UNA, 100.0);
        $cuota = $plan->cuotas()->firstOrFail();

        Storage::fake('public');

        $this->actingAs($usuario);

        Volt::test('pagos.mi-cuenta')
            ->set("montoPorCuota.{$cuota->id}", '100')
            ->set("metodoPorCuota.{$cuota->id}", 'yape')
            ->set("comprobantePorCuota.{$cuota->id}", UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->call('subirComprobante', $cuota->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'cuota_id' => $cuota->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Regresión: "Mi cuenta" hardcodeaba el monto del pago al monto
     * completo de la cuota sin importar cuánto haya escrito el
     * estudiante -- un pago parcial terminaba restando la cuota entera
     * al total adeudado. Ver mi-cuenta.blade.php::subirComprobante().
     */
    public function test_un_pago_parcial_desde_mi_cuenta_no_marca_la_cuota_como_pagada(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $plan = $this->app->make(PlanPagoService::class)->crear($matricula, NumeroCuotasEnum::UNA, 80.0);
        $cuota = $plan->cuotas()->firstOrFail();

        Storage::fake('public');

        $this->actingAs($usuario);

        Volt::test('pagos.mi-cuenta')
            ->set("montoPorCuota.{$cuota->id}", '40')
            ->set("metodoPorCuota.{$cuota->id}", 'yape')
            ->set("comprobantePorCuota.{$cuota->id}", UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->call('subirComprobante', $cuota->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'cuota_id' => $cuota->id,
            'monto' => 40.0,
            'estado' => 'pendiente',
        ]);

        $pago = Pago::query()->where('cuota_id', $cuota->id)->firstOrFail();

        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $this->actingAs($tesoreria);

        Volt::test('pagos.index')
            ->call('aprobar', $pago->id)
            ->assertHasNoErrors();

        $cuota->refresh();
        $this->assertSame('pendiente', $cuota->estado->value);
        $this->assertSame(40.0, $cuota->saldoPendiente());
    }

    /**
     * Pedido del cliente: que el sistema "vaya descontando" el saldo de
     * una cuota -- al volver a elegir un estudiante con un pago parcial ya
     * aprobado, el monto debe salir precargado con lo que falta (S/40),
     * no con el total de la cuota (S/80), para que Tesorería no tenga que
     * calcularlo a mano.
     */
    public function test_seleccionar_estudiante_precarga_el_monto_con_el_saldo_pendiente_de_la_cuota(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        $concepto = ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $plan = $this->app->make(PlanPagoService::class)->crear($matricula, NumeroCuotasEnum::UNA, 80.0);
        $cuota = $plan->cuotas()->firstOrFail();

        $pagoService = $this->app->make(PagoService::class);
        $primerPago = $pagoService->registrar($estudiante, $concepto, [['monto' => 40.0, 'metodo' => 'yape']], $cuota, null, null);
        $pagoService->aprobar($primerPago, User::factory()->create()->id, SerieReciboEnum::ORIGINAL);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->assertSet('partes.0.monto', '40');
    }

    /**
     * No debe pisar un monto que la persona ya haya empezado a escribir
     * antes de elegir el concepto (ej. si cambia de estudiante a mitad de
     * llenar el formulario).
     */
    public function test_el_autocompletado_del_saldo_no_pisa_un_monto_ya_escrito(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        $concepto = ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $this->app->make(PlanPagoService::class)->crear($matricula, NumeroCuotasEnum::UNA, 80.0);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->set('partes.0.monto', '15')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->assertSet('partes.0.monto', '15');
    }

    /**
     * Pedido del cliente: poder anotar de quién es la cuenta que recibió
     * el pago (ej. Yape personal de un miembro del staff, no la cuenta
     * institucional) sin reemplazar el método -- ver
     * PagoParte::metodoConNota().
     */
    public function test_registrar_un_pago_con_nota_en_el_metodo_queda_guardada_y_se_muestra_junto_al_metodo(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '40')
            ->set('partes.0.metodo', 'yape')
            ->set('partes.0.nota', 'Walter')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertDatabaseHas('pago_partes', ['pago_id' => $pago->id, 'metodo' => 'yape', 'nota' => 'Walter']);
        $this->assertSame('Yape Walter', $pago->medioPagoResumen());
        $this->assertSame('Yape Walter', $pago->partes->first()->metodoConNota());
    }

    /**
     * Pedido del cliente: la fecha de pago debe poder editarse (ej. un
     * cobro en efectivo recibido unos días antes de registrarse), a
     * diferencia de la fecha de emisión del recibo, que sigue siendo
     * siempre la de hoy sin importar esto.
     */
    public function test_registrar_un_pago_con_fecha_de_pago_pasada(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->assertSet('fechaPago', now()->format('Y-m-d'))
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '40')
            ->set('partes.0.metodo', 'efectivo')
            ->set('fechaPago', '2026-09-01')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame('2026-09-01', $pago->fecha_pago->format('Y-m-d'));
    }

    /**
     * Pedido del cliente: al captar un estudiante se registran cobros
     * futuros puntuales (Convalidación, Exoneración...) que deben poder
     * cobrarse desde Registrar pago, y el estado solo debe pasar a
     * "pagado" cuando el saldo real llega a 0 -- mismo criterio de "no
     * huecos" que las cuotas de mensualidad.
     */
    public function test_cobrar_un_cargo_adicional_pasa_a_pagado_solo_cuando_el_saldo_llega_a_cero(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $estudiante = Estudiante::factory()->create();
        ConceptoPago::factory()->create(['tipo' => 'otro']);
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'concepto' => 'Convalidación', 'monto' => 80]);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->call('seleccionarCargoAdicional', $cargo->id)
            ->assertSet('partes.0.monto', '80')
            ->set('partes.0.monto', '40')
            ->set('partes.0.metodo', 'yape')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $primerPago = Pago::query()->where('cargo_adicional_id', $cargo->id)->firstOrFail();
        $this->assertSame('40.00', $primerPago->monto);
        $this->assertSame('Convalidación', $primerPago->nombreConcepto());

        $this->actingAs($tesoreria);
        Volt::test('pagos.index')->call('aprobar', $primerPago->id)->assertHasNoErrors();

        $cargo->refresh();
        $this->assertSame('pendiente', $cargo->estado->value);
        $this->assertSame(40.0, $cargo->saldoPendiente());

        $this->actingAs($administrativo);
        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->call('seleccionarCargoAdicional', $cargo->id)
            ->assertSet('partes.0.monto', '40')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $segundoPago = Pago::query()->where('cargo_adicional_id', $cargo->id)->where('id', '!=', $primerPago->id)->firstOrFail();

        $this->actingAs($tesoreria);
        Volt::test('pagos.index')->call('aprobar', $segundoPago->id)->assertHasNoErrors();

        $cargo->refresh();
        $this->assertSame('pagado', $cargo->estado->value);
        $this->assertSame(0.0, $cargo->saldoPendiente());
    }

    /**
     * Elegir un cargo adicional es excluyente con elegir un concepto del
     * catálogo -- limpiarCargoAdicional() debe poder volver al flujo
     * normal sin arrastrar el cargo elegido antes.
     */
    public function test_limpiar_el_cargo_adicional_elegido_permite_volver_al_concepto_del_catalogo(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->call('seleccionarCargoAdicional', $cargo->id)
            ->assertSet('cargoAdicionalId', $cargo->id)
            ->call('limpiarCargoAdicional')
            ->assertSet('cargoAdicionalId', null)
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '30')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'concepto_id' => $concepto->id,
            'cargo_adicional_id' => null,
        ]);
    }

    public function test_no_permite_una_fecha_de_pago_futura(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('partes.0.monto', '40')
            ->set('partes.0.metodo', 'efectivo')
            ->set('fechaPago', now()->addDay()->format('Y-m-d'))
            ->call('registrarPago')
            ->assertHasErrors('fechaPago');

        $this->assertDatabaseMissing('pagos', ['estudiante_id' => $estudiante->id]);
    }
}
