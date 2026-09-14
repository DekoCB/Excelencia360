<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mi perfil</h1>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
            <livewire:profile.active-sessions-form />
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
            <livewire:profile.two-factor-authentication-form />
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
