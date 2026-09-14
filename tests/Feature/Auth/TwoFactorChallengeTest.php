<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identidad\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon2faActivo(): array
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->generarSecreto($user);
        $user->refresh();
        $service->confirmar($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));

        return [$user->refresh(), $service];
    }

    public function test_un_usuario_sin_2fa_inicia_sesion_directo(): void
    {
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.nombre', 'Quien Ingresa')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_un_usuario_con_2fa_activo_es_redirigido_al_reto_sin_quedar_autenticado(): void
    {
        [$user] = $this->usuarioCon2faActivo();

        Volt::test('pages.auth.login')
            ->set('form.nombre', 'Quien Ingresa')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
        $this->assertSame('Quien Ingresa', session('login.nombre'));
    }

    public function test_el_reto_con_un_codigo_totp_valido_completa_el_login(): void
    {
        [$user] = $this->usuarioCon2faActivo();
        session(['login.id' => $user->id]);

        $codigo = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);

        Volt::test('pages.auth.two-factor-challenge')
            ->set('codigo', $codigo)
            ->call('verificar')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.id'));
    }

    public function test_el_reto_con_un_codigo_de_recuperacion_completa_el_login_y_lo_consume(): void
    {
        [$user, $service] = $this->usuarioCon2faActivo();
        session(['login.id' => $user->id]);

        $codigoRecuperacion = $user->two_factor_recovery_codes[0];

        Volt::test('pages.auth.two-factor-challenge')
            ->set('codigo', $codigoRecuperacion)
            ->call('verificar')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($service->verificarCodigo($user->refresh(), $codigoRecuperacion));
    }

    public function test_el_reto_con_un_codigo_invalido_no_autentica_y_queda_auditado(): void
    {
        [$user] = $this->usuarioCon2faActivo();
        session(['login.id' => $user->id]);

        Volt::test('pages.auth.two-factor-challenge')
            ->set('codigo', '000000')
            ->call('verificar')
            ->assertHasErrors('codigo');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'two_factor_failed',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_sin_una_sesion_de_login_pendiente_redirige_a_login(): void
    {
        Volt::test('pages.auth.two-factor-challenge')
            ->assertRedirect(route('login'));
    }

    public function test_un_login_fallido_queda_auditado(): void
    {
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.nombre', 'Quien Ingresa')
            ->set('form.email', $user->email)
            ->set('form.password', 'contraseña-incorrecta')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_un_login_fallido_con_email_inexistente_queda_auditado_sin_usuario(): void
    {
        Volt::test('pages.auth.login')
            ->set('form.nombre', 'Quien Ingresa')
            ->set('form.email', 'nadie@ceba.test')
            ->set('form.password', 'lo-que-sea')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'auditable_id' => 0,
        ]);
    }
}
