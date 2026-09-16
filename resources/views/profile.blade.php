<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mi perfil</h1>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        @php
            $personaConQr = auth()->user()->estudiante ?? auth()->user()->docente;
        @endphp
        @if ($personaConQr)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4 sm:p-6">
                <h2 class="font-display text-lg text-ink">Mi código de asistencia</h2>
                <p class="mt-1 text-sm text-ink-dim">
                    Muéstralo para que
                    {{ auth()->user()->estudiante ? 'tu docente' : 'el personal de oficina' }}
                    registre tu asistencia al escanearlo.
                </p>
                <div class="mt-4 inline-block rounded-xl border border-border bg-white p-3">
                    <img
                        src="{{ \App\Shared\Support\QrCode::pngBase64($personaConQr->obtenerOCrearQrToken(), 220) }}"
                        alt="Código QR de asistencia"
                        class="h-44 w-44"
                    >
                </div>
            </div>
        @endif

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
