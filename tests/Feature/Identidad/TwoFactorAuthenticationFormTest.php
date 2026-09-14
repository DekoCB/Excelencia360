<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_habilitar_genera_un_secreto_y_muestra_el_qr(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('profile.two-factor-authentication-form')
            ->call('habilitar')
            ->assertSet('habilitando', true);

        $this->assertNotNull($user->refresh()->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_confirmar_con_el_codigo_correcto_activa_2fa_y_muestra_codigos_de_recuperacion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('profile.two-factor-authentication-form')->call('habilitar');

        $codigo = app(Google2FA::class)->getCurrentOtp($user->refresh()->two_factor_secret);

        $component->set('codigoConfirmacion', $codigo)
            ->call('confirmar')
            ->assertSet('habilitando', false)
            ->assertSet('mostrandoCodigos', true);

        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);
        $this->assertCount(8, $component->get('codigosRecuperacion'));
    }

    public function test_confirmar_con_un_codigo_incorrecto_no_activa_2fa(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('profile.two-factor-authentication-form')
            ->call('habilitar')
            ->set('codigoConfirmacion', '000000')
            ->call('confirmar')
            ->assertHasErrors('codigoConfirmacion');

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_deshabilitar_exige_la_contrasena_actual(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));

        Volt::test('profile.two-factor-authentication-form')
            ->set('passwordActual', 'contraseña-incorrecta')
            ->call('deshabilitar')
            ->assertHasErrors('passwordActual');

        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_deshabilitar_con_la_contrasena_correcta_desactiva_2fa(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));

        Volt::test('profile.two-factor-authentication-form')
            ->set('passwordActual', 'password')
            ->call('deshabilitar')
            ->assertHasNoErrors();

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_secret);
    }
}
