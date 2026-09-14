<?php

namespace App\Livewire\Forms;

use App\Shared\Enums\CategoriaAccesoEnum;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    // Varias cuentas institucionales (p. ej. Dirección) las usa más de una
    // persona -- este nombre es lo que después identifica quién estuvo
    // detrás de cada sesión en "Sesiones activas", ver SessionControlService.
    #[Validate('required|string|max:150')]
    public string $nombre = '';

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return bool True si las credenciales son correctas pero falta el
     *              segundo factor: el login queda en sesión pendiente
     *              (ver two-factor-challenge) en vez de completarse aquí.
     *
     * @throws ValidationException
     */
    public function authenticate(?CategoriaAccesoEnum $categoria = null): bool
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        if (! $user->estaActivo()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'form.email' => 'Tu cuenta está desactivada. Contacta a Dirección si crees que es un error.',
            ]);
        }

        // El usuario eligió una puerta de entrada (Estudiante / Personal) antes
        // de escribir sus credenciales: aunque email y contraseña sean
        // correctos, si su rol real no pertenece a esa categoría se rechaza,
        // para que no entre por la puerta equivocada.
        if ($categoria !== null && ! $categoria->incluyeA($user)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'form.email' => 'Estas credenciales no corresponden a ese tipo de acceso.',
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            Auth::logout();

            session([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
                'login.nombre' => $this->nombre,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
