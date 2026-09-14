<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generar_secreto_guarda_un_secreto_sin_confirmar(): void
    {
        $user = User::factory()->create();

        $secreto = app(TwoFactorAuthenticationService::class)->generarSecreto($user);

        $user->refresh();
        $this->assertSame($secreto, $user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_confirmar_con_un_codigo_valido_activa_2fa_y_genera_codigos_de_recuperacion(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();

        $codigoValido = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);

        $codigos = $service->confirmar($user, $codigoValido);

        $this->assertNotNull($codigos);
        $this->assertCount(8, $codigos);
        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame($codigos, $user->two_factor_recovery_codes);
    }

    public function test_confirmar_con_un_codigo_invalido_no_activa_2fa(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();

        $resultado = $service->confirmar($user, '000000');

        $this->assertNull($resultado);
        $this->assertNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_deshabilitar_limpia_los_tres_campos(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));

        $service->deshabilitar($user);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_verificar_codigo_acepta_un_totp_valido(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));
        $user->refresh();

        $this->assertTrue($service->verificarCodigo($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret)));
    }

    public function test_verificar_codigo_consume_un_codigo_de_recuperacion_una_sola_vez(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $codigos = $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));
        $user->refresh();

        $codigoRecuperacion = $codigos[0];

        $this->assertTrue($service->verificarCodigo($user, $codigoRecuperacion));
        $this->assertFalse($service->verificarCodigo($user->refresh(), $codigoRecuperacion));
        $this->assertCount(7, $user->refresh()->two_factor_recovery_codes);
    }

    public function test_regenerar_codigos_de_recuperacion_invalida_los_anteriores(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $codigosOriginales = $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));
        $user->refresh();

        $codigosNuevos = $service->regenerarCodigosRecuperacion($user);

        $this->assertNotSame($codigosOriginales, $codigosNuevos);
        $this->assertFalse($service->verificarCodigo($user->refresh(), $codigosOriginales[0]));
        $this->assertTrue($service->verificarCodigo($user->refresh(), $codigosNuevos[0]));
    }
}
