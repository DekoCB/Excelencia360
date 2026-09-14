<?php

use App\Livewire\Forms\LoginForm;
use App\Modules\Identidad\Services\SessionControlService;
use App\Shared\Enums\CategoriaAccesoEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.login')] class extends Component
{
    public LoginForm $form;

    public ?string $categoria = null;

    public function elegirCategoria(string $categoria): void
    {
        $this->categoria = CategoriaAccesoEnum::from($categoria)->value;
    }

    public function cambiarCategoria(): void
    {
        $this->categoria = null;
        $this->form->reset();
        $this->resetErrorBag();
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(SessionControlService $sesiones): void
    {
        $this->validate();

        $categoria = $this->categoria ? CategoriaAccesoEnum::from($this->categoria) : null;

        if ($this->form->authenticate($categoria)) {
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Session::regenerate();

        $sesiones->registrarIngreso(Auth::user(), $this->form->nombre, session()->getId(), Request::ip());

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    @if (! $categoria)
        <div class="text-center">
            <h2 class="font-sans text-lg font-bold text-ink">¿Cómo deseas ingresar?</h2>
            <p class="mt-1 text-sm text-ink-faint">Elige tu tipo de acceso para continuar.</p>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach (CategoriaAccesoEnum::cases() as $opcion)
                <button
                    type="button"
                    wire:click="elegirCategoria('{{ $opcion->value }}')"
                    class="group rounded-2xl border border-border bg-surface-2 p-6 text-center transition hover:-translate-y-0.5 hover:border-accent/40 hover:bg-accent-soft"
                >
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent transition duration-300 group-hover:scale-110">
                        @if ($opcion === CategoriaAccesoEnum::ESTUDIANTE)
                            <x-heroicon-o-academic-cap class="h-6 w-6" />
                        @else
                            <x-heroicon-o-briefcase class="h-6 w-6" />
                        @endif
                    </span>
                    <span class="mt-3 block font-sans text-sm font-bold text-ink">{{ $opcion->label() }}</span>
                    <span class="mt-1 block text-xs text-ink-faint">
                        @if ($opcion === CategoriaAccesoEnum::ESTUDIANTE)
                            Matrícula, notas y asistencia
                        @else
                            Docentes, coordinación, dirección, tesorería y administrativo
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    @else
        <button
            type="button"
            wire:click="cambiarCategoria"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-faint transition hover:text-ink"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            Cambiar tipo de acceso · {{ CategoriaAccesoEnum::from($categoria)->label() }}
        </button>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="login">
            <!-- Nombre de quien ingresa -->
            <div>
                <x-input-label for="nombre" value="¿Con qué nombre ingresas?" />
                <x-text-input wire:model="form.nombre" id="nombre" class="block mt-1 w-full" type="text" name="nombre" required autofocus autocomplete="off" placeholder="Tu nombre y apellido" />
                <x-input-error :messages="$errors->get('form.nombre')" class="mt-2" />
            </div>

            <!-- Usuario -->
            <div class="mt-4">
                <x-input-label for="email" :value="__('Usuario')" />
                <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="text" name="email" required autocomplete="username" />
                <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="mt-4" x-data="{ mostrar: false }">
                <x-input-label for="password" :value="__('Password')" />

                <div class="relative mt-1">
                    <x-text-input wire:model="form.password" id="password" class="block w-full pr-10"
                                    type="password"
                                    x-bind:type="mostrar ? 'text' : 'password'"
                                    name="password"
                                    required autocomplete="current-password" />

                    <button
                        type="button"
                        x-on:click="mostrar = ! mostrar"
                        class="absolute inset-y-0 right-0 flex items-center px-2.5 text-ink-faint transition hover:text-accent"
                        x-bind:aria-label="mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                        tabindex="-1"
                    >
                        <x-heroicon-o-eye-slash x-show="mostrar" x-cloak class="h-4 w-4" />
                        <x-heroicon-o-eye x-show="! mostrar" class="h-4 w-4" />
                    </button>
                </div>

                <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-border bg-surface text-accent shadow-sm focus:ring-accent" name="remember">
                    <span class="ms-2 text-sm text-ink-dim">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-ink-dim hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
