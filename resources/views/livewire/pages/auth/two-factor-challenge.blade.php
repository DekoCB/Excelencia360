<?php

use App\Models\User;
use App\Modules\Identidad\Jobs\RegistrarAuditoriaJob;
use App\Modules\Identidad\Services\SessionControlService;
use App\Modules\Identidad\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $codigo = '';

    public function mount(): void
    {
        if (! session('login.id')) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function verificar(TwoFactorAuthenticationService $service, SessionControlService $sesiones): void
    {
        $this->validate(['codigo' => 'required|string|max:30']);

        $userId = session('login.id');

        if (! $userId) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $throttleKey = 'two-factor:'.$userId;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'codigo' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
            ]);
        }

        $user = User::query()->find($userId);

        if (! $user || ! $service->verificarCodigo($user, $this->codigo)) {
            RateLimiter::hit($throttleKey);

            RegistrarAuditoriaJob::dispatch(
                $user?->id,
                'two_factor_failed',
                User::class,
                $user?->id ?? 0,
                [],
                [],
                Request::ip(),
                substr((string) Request::userAgent(), 0, 255),
            );

            Log::channel('seguridad')->warning('Código de 2FA inválido', [
                'usuario_id' => $user?->id,
                'ip' => Request::ip(),
            ]);

            throw ValidationException::withMessages([
                'codigo' => 'El código no es válido.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $nombre = (string) session('login.nombre', $user->name);

        Auth::login($user, (bool) session('login.remember', false));

        session()->forget(['login.id', 'login.remember', 'login.nombre']);
        Session::regenerate();

        $sesiones->registrarIngreso($user, $nombre, session()->getId(), Request::ip());

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <p class="mb-4 text-sm text-ink-dim">
        Ingresa el código de tu app autenticadora, o uno de tus códigos de recuperación si no tienes acceso a ella.
    </p>

    <form wire:submit="verificar">
        <x-input-label for="codigo" value="Código" />
        <x-text-input
            wire:model="codigo"
            id="codigo"
            class="block mt-1 w-full"
            autofocus
            autocomplete="one-time-code"
            inputmode="numeric"
        />
        <x-input-error :messages="$errors->get('codigo')" class="mt-2" />

        <div class="flex justify-end mt-4">
            <x-primary-button>Verificar</x-primary-button>
        </div>
    </form>
</div>
