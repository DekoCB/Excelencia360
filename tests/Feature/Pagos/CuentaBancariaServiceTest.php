<?php

namespace Tests\Feature\Pagos;

use App\Modules\Pagos\Enums\MedioCuentaEnum;
use App\Modules\Pagos\Enums\TipoBilleteraEnum;
use App\Modules\Pagos\Models\CuentaBancaria;
use App\Modules\Pagos\Services\CuentaBancariaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaBancariaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CuentaBancariaService
    {
        return $this->app->make(CuentaBancariaService::class);
    }

    public function test_crear_una_cuenta_bancaria_guarda_banco_numero_y_cci(): void
    {
        $cuenta = $this->service()->crear(MedioCuentaEnum::BANCO, 'BCP', '194-123', '00219400123', null, null, 'CEBA E.I.R.L.', null);

        $this->assertSame(MedioCuentaEnum::BANCO, $cuenta->medio);
        $this->assertSame('BCP', $cuenta->banco);
        $this->assertSame('194-123', $cuenta->numero_cuenta);
        $this->assertSame('00219400123', $cuenta->cci);
        $this->assertNull($cuenta->tipo_billetera);
        $this->assertNull($cuenta->celular);
        $this->assertTrue($cuenta->activa);
    }

    public function test_crear_una_billetera_guarda_tipo_y_celular_sin_datos_bancarios(): void
    {
        $cuenta = $this->service()->crear(MedioCuentaEnum::BILLETERA, null, null, null, TipoBilleteraEnum::YAPE, '987654321', 'CEBA E.I.R.L.', null);

        $this->assertSame(MedioCuentaEnum::BILLETERA, $cuenta->medio);
        $this->assertSame(TipoBilleteraEnum::YAPE, $cuenta->tipo_billetera);
        $this->assertSame('987654321', $cuenta->celular);
        $this->assertNull($cuenta->banco);
        $this->assertNull($cuenta->numero_cuenta);
    }

    public function test_actualizar_puede_cambiar_una_cuenta_bancaria_a_billetera(): void
    {
        $cuenta = $this->service()->crear(MedioCuentaEnum::BANCO, 'BCP', '194-123', null, null, null, 'CEBA E.I.R.L.', null);

        $actualizada = $this->service()->actualizar(
            $cuenta,
            MedioCuentaEnum::BILLETERA,
            null,
            null,
            null,
            TipoBilleteraEnum::PLIN,
            '912345678',
            'CEBA E.I.R.L.',
            true,
            null,
        );

        $this->assertSame(MedioCuentaEnum::BILLETERA, $actualizada->medio);
        $this->assertSame(TipoBilleteraEnum::PLIN, $actualizada->tipo_billetera);
        $this->assertSame('912345678', $actualizada->celular);
    }

    public function test_actualizar_puede_desactivar_una_cuenta(): void
    {
        $cuenta = $this->service()->crear(MedioCuentaEnum::BANCO, 'BCP', '194-123', null, null, null, 'CEBA E.I.R.L.', null);

        $this->service()->actualizar($cuenta, MedioCuentaEnum::BANCO, 'BCP', '194-123', null, null, null, 'CEBA E.I.R.L.', false, null);

        $this->assertFalse($cuenta->fresh()->activa);
    }

    public function test_activas_solo_incluye_cuentas_activas_de_cualquier_medio(): void
    {
        $activaBanco = $this->service()->crear(MedioCuentaEnum::BANCO, 'BCP', '194-123', null, null, null, 'CEBA E.I.R.L.', null);
        $activaBilletera = $this->service()->crear(MedioCuentaEnum::BILLETERA, null, null, null, TipoBilleteraEnum::YAPE, '987654321', 'CEBA E.I.R.L.', null);
        $inactiva = CuentaBancaria::factory()->create(['activa' => false]);

        $activas = $this->service()->activas();

        $this->assertTrue($activas->contains('id', $activaBanco->id));
        $this->assertTrue($activas->contains('id', $activaBilletera->id));
        $this->assertFalse($activas->contains('id', $inactiva->id));
    }
}
