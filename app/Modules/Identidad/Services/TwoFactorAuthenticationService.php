<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * TOTP compatible con Google Authenticator / Authy / 1Password, con
 * códigos de recuperación de un solo uso. El secreto y los códigos se
 * guardan cifrados (ver casts en App\Models\User).
 */
class TwoFactorAuthenticationService
{
    private const CANTIDAD_CODIGOS_RECUPERACION = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * Genera un secreto nuevo (sin confirmar todavía) y lo guarda. Cualquier
     * secreto/código de recuperación previo queda invalidado.
     */
    public function generarSecreto(User $user): string
    {
        $secreto = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secreto,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secreto;
    }

    /**
     * Data URL (SVG inline) del QR para escanear con la app autenticadora.
     */
    public function qrCodeDataUrl(User $user): string
    {
        return $this->google2fa->getQRCodeInline(
            (string) config('app.name'),
            $user->email,
            (string) $user->two_factor_secret,
        );
    }

    /**
     * Confirma el secreto pendiente con el primer código válido que
     * ingresa el usuario, activa 2FA y genera los códigos de recuperación.
     *
     * @return list<string>|null Los códigos de recuperación en claro (una sola vez), o null si el código no era válido.
     */
    public function confirmar(User $user, string $codigo): ?array
    {
        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $codigo)) {
            return null;
        }

        $codigosRecuperacion = $this->generarCodigosRecuperacion();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codigosRecuperacion,
        ])->save();

        return $codigosRecuperacion;
    }

    public function deshabilitar(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * @return list<string> Los códigos en claro (una sola vez).
     */
    public function regenerarCodigosRecuperacion(User $user): array
    {
        $codigos = $this->generarCodigosRecuperacion();

        $user->forceFill(['two_factor_recovery_codes' => $codigos])->save();

        return $codigos;
    }

    /**
     * Verifica un código TOTP o, si no lo es, intenta consumir un código de
     * recuperación (de un solo uso).
     */
    public function verificarCodigo(User $user, string $codigo): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, $codigo)) {
            return true;
        }

        return $this->consumirCodigoRecuperacion($user, $codigo);
    }

    private function consumirCodigoRecuperacion(User $user, string $codigo): bool
    {
        $codigos = $user->two_factor_recovery_codes ?? [];

        if (! in_array($codigo, $codigos, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codigos, [$codigo])),
        ])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    private function generarCodigosRecuperacion(): array
    {
        return Collection::times(
            self::CANTIDAD_CODIGOS_RECUPERACION,
            fn () => Str::random(10).'-'.Str::random(10),
        )->all();
    }
}
