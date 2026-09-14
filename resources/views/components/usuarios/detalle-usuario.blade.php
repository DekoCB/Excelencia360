@props(['usuario', 'rolesDisponibles', 'puedeGestionarRoles', 'puedeGestionarSesiones', 'sesiones', 'puedeVerAuditoria', 'historial'])

{{--
    Contenido del detalle de usuario, compartido entre la página completa
    (usuarios/show.blade.php, para acceso directo por URL) y el modal "Ver"
    de usuarios/index.blade.php. Los wire:submit/wire:click llaman al
    componente Livewire que envuelve este parcial, así que ambos (show y el
    modal) deben exponer guardarDatos(), cambiarRol(), revocarSesion() y los
    campos name/email/dni/phone/estado/rol.
--}}
<div class="space-y-6">
    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Datos personales</h2>

        <form wire:submit="guardarDatos" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Nombre completo" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" value="Correo" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dni" value="DNI" />
                    <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="phone" value="Celular" />
                    <x-text-input wire:model="phone" id="phone" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <x-select-input
                        wire:model="estado"
                        id="estado"
                        class="mt-1 block w-full"
                        :disabled="Gate::denies('usuarios.editar', $usuario)"
                        :options="collect(\App\Shared\Enums\EstadoUsuarioEnum::cases())->mapWithKeys(fn ($estadoOpcion) => [$estadoOpcion->value => $estadoOpcion->label()])"
                    />
                </div>
            </div>

            @can('usuarios.editar', $usuario)
                <div class="flex justify-end">
                    <x-primary-button type="submit">Guardar cambios</x-primary-button>
                </div>
            @endcan
        </form>
    </div>

    @if ($puedeGestionarRoles)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Rol institucional</h2>
            <form wire:submit="cambiarRol" class="mt-4 flex items-end gap-3">
                <div class="flex-1">
                    <x-select-input
                        wire:model="rol"
                        class="block w-full"
                        :options="collect($rolesDisponibles)->mapWithKeys(fn ($rolOpcion) => [$rolOpcion->value => $rolOpcion->label()])"
                    />
                </div>
                <x-primary-button type="submit">Actualizar rol</x-primary-button>
            </form>
        </div>
    @endif

    @if ($puedeGestionarSesiones)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Sesiones activas</h2>
            <div class="mt-4 divide-y divide-border">
                @forelse ($sesiones as $sesion)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="text-ink">
                                {{ $sesion->ipAddress ?? 'IP desconocida' }}
                                @if ($sesion->esActual)
                                    <span class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">Sesión actual</span>
                                @endif
                            </p>
                            <p class="text-xs text-ink-faint">
                                {{ \Illuminate\Support\Str::limit($sesion->userAgent ?? 'Agente desconocido', 60) }}
                                · última actividad {{ \Illuminate\Support\Carbon::createFromTimestamp($sesion->lastActivity)->diffForHumans() }}
                            </p>
                        </div>
                        @unless ($sesion->esActual)
                            <button
                                x-on:click="$store.confirm.preguntar('¿Cerrar esta sesión?', () => $wire.revocarSesion(@js($sesion->id)), { peligro: true, etiquetaConfirmar: 'Cerrar sesión' })"
                                class="text-sm font-medium text-danger hover:underline"
                            >
                                Cerrar sesión
                            </button>
                        @endunless
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">No hay sesiones activas registradas.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($puedeVerAuditoria)
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="text-sm font-semibold text-ink">Actividad reciente de esta cuenta</h2>
            <div class="mt-4 divide-y divide-border">
                @forelse ($historial as $entrada)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span class="text-ink-dim">
                            {{ match ($entrada->event) {
                                'created' => 'Cuenta creada',
                                'updated' => 'Datos actualizados',
                                'deleted' => 'Cuenta eliminada',
                                default => $entrada->event,
                            } }}
                        </span>
                        <span class="text-xs text-ink-faint">{{ $entrada->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">Sin actividad registrada todavía.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
