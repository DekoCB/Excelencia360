<?php

namespace Tests\Feature\FlujoCaja;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Pagos\Models\Pago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FlujoCajaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_tesoreria_puede_ver_flujo_de_caja(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)->get(route('flujo-caja.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_flujo_de_caja(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)->get(route('flujo-caja.index'))->assertForbidden();
    }

    public function test_coordinador_puede_ver_pero_no_registrar_egresos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('flujo-caja.index'))->assertOk();

        rescue(fn () => Volt::test('flujo-caja.index')
            ->set('categoria', 'otro')
            ->set('monto', '10')
            ->set('metodo', 'efectivo')
            ->set('fechaEgreso', now()->format('Y-m-d'))
            ->call('registrarEgreso'), report: false);

        $this->assertDatabaseCount('egresos', 0);
    }

    public function test_tesoreria_registra_un_egreso_con_comprobante_desde_la_ui(): void
    {
        Storage::fake('public');

        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria);

        Volt::test('flujo-caja.index')
            ->call('abrirModal')
            ->set('categoria', 'servicios')
            ->set('descripcion', 'Internet de setiembre')
            ->set('monto', '120')
            ->set('metodo', 'transferencia')
            ->set('fechaEgreso', now()->format('Y-m-d'))
            ->set('comprobante', UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'))
            ->call('registrarEgreso')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $this->assertDatabaseHas('egresos', ['categoria' => 'servicios', 'monto' => '120.00']);
    }

    public function test_el_resumen_muestra_ingresos_egresos_y_saldo_del_mes(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        Pago::factory()->aprobado()->create(['monto' => 200, 'fecha_aprobacion' => now()]);

        $this->actingAs($tesoreria);

        Volt::test('flujo-caja.index')
            ->assertSee('200.00')
            ->call('mesAnterior')
            ->assertDontSee('200.00');
    }

    public function test_exportar_pdf_devuelve_una_descarga(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        Pago::factory()->aprobado()->create(['monto' => 200, 'fecha_aprobacion' => now()]);

        $this->actingAs($tesoreria);

        $testable = Volt::test('flujo-caja.index')->call('exportarPdf');

        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }

    public function test_tesoreria_abre_y_cierra_un_turno_de_caja_desde_la_ui(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria);

        Volt::test('flujo-caja.index')
            ->call('abrirModalTurno')
            ->set('montoInicial', '100')
            ->set('turnoNuevo', 'manana')
            ->set('responsableNuevo', 'Juana Pérez')
            ->call('abrirTurno')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalAbrirTurno', false);

        $this->assertDatabaseHas('turnos_caja', [
            'usuario_id' => $tesoreria->id,
            'turno' => 'manana',
            'responsable' => 'Juana Pérez',
            'monto_inicial' => '100.00',
            'estado' => 'abierto',
        ]);

        Volt::test('flujo-caja.index')
            ->call('abrirModalCerrarTurno')
            ->set('montoFinal', '100')
            ->call('cerrarTurno')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCerrarTurno', false);

        $this->assertDatabaseHas('turnos_caja', [
            'usuario_id' => $tesoreria->id,
            'estado' => 'cerrado',
            'monto_final_declarado' => '100.00',
        ]);
    }

    public function test_coordinador_no_puede_abrir_un_turno_de_caja(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        rescue(fn () => Volt::test('flujo-caja.index')
            ->set('montoInicial', '100')
            ->set('turnoNuevo', 'manana')
            ->set('responsableNuevo', 'Juana Pérez')
            ->call('abrirTurno'), report: false);

        $this->assertDatabaseCount('turnos_caja', 0);
    }
}
