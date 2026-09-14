<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public $foto = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'foto' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);
        $user->save();

        if ($this->foto) {
            $user->addMedia($this->foto->getRealPath())
                ->usingFileName('avatar-'.$user->id.'.'.$this->foto->getClientOriginalExtension())
                ->preservingOriginal()
                ->toMediaCollection('avatar');

            $this->foto = null;
        }

        $this->dispatch('profile-updated', name: $user->name, avatarUrl: $this->urlAvatar($user));
    }

    public function quitarFoto(): void
    {
        $user = Auth::user();
        $user->clearMediaCollection('avatar');

        $this->dispatch('profile-updated', name: $user->name, avatarUrl: null);
    }

    private function urlAvatar(User $user): ?string
    {
        $media = $user->getFirstMedia('avatar');

        return $media ? $media->getUrl().'?v='.$media->updated_at->timestamp : null;
    }

    public function with(): array
    {
        return [
            'avatarUrl' => $this->urlAvatar(Auth::user()),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-ink-dim">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div class="flex items-center gap-4">
            @if ($foto && $foto->isPreviewable())
                <img src="{{ $foto->temporaryUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover">
            @elseif ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="" class="h-16 w-16 rounded-full object-cover">
            @else
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-accent-soft text-lg font-bold uppercase text-accent">
                    {{ collect(explode(' ', trim($name)))->map(fn ($parte) => mb_substr($parte, 0, 1))->take(2)->implode('') }}
                </span>
            @endif

            <div>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 text-sm font-medium text-ink transition hover:bg-surface-2">
                    <x-heroicon-o-camera class="h-4 w-4" />
                    {{ __('Cambiar foto') }}
                    <input type="file" wire:model="foto" accept="image/*" class="hidden">
                </label>

                <span wire:loading wire:target="foto" class="ms-2 text-xs text-ink-faint">{{ __('Subiendo…') }}</span>

                @if ($avatarUrl && ! $foto)
                    <button type="button" x-on:click="$store.confirm.preguntar(@js(__('¿Quitar tu foto de perfil?')), () => $wire.quitarFoto(), { peligro: true, etiquetaConfirmar: @js(__('Quitar foto')) })" class="ms-2 text-xs text-danger hover:underline">
                        {{ __('Quitar foto') }}
                    </button>
                @endif

                <x-input-error class="mt-2" :messages="$errors->get('foto')" />
            </div>
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
