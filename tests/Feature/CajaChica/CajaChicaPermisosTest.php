<?php

namespace Tests\Feature\CajaChica;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CajaChicaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tesoreria_puede_ver_caja_chica(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('caja-chica.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_caja_chica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('caja-chica.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_ver_pero_no_registrar_gastos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('caja-chica.index'))->assertOk();

        rescue(fn () => Volt::test('caja-chica.index')
            ->set('categoria', 'otro')
            ->set('monto', '10')
            ->set('fechaGasto', now()->format('Y-m-d'))
            ->call('registrarGasto'), report: false);

        $this->assertDatabaseCount('caja_chica_movimientos', 0);
    }

    public function test_tesoreria_asigna_el_fondo_y_registra_un_gasto_desde_la_ui(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria);

        Volt::test('caja-chica.index')
            ->call('abrirModalFondo')
            ->set('montoFondo', '500')
            ->call('registrarFondo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('caja_chica_movimientos', [
            'tipo' => 'asignacion',
            'monto' => '500.00',
        ]);

        Volt::test('caja-chica.index')
            ->call('abrirModalGasto')
            ->set('categoria', 'materiales')
            ->set('monto', '30')
            ->set('fechaGasto', now()->format('Y-m-d'))
            ->call('registrarGasto')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('caja_chica_movimientos', [
            'tipo' => 'gasto',
            'monto' => '30.00',
        ]);
    }

    public function test_exportar_pdf_devuelve_una_descarga(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria);

        $testable = Volt::test('caja-chica.index')->call('exportarPdf');

        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }
}
