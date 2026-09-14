<?php

use App\Modules\Identidad\Services\RolePermissionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    /** @var array<string, list<string>> */
    public array $matriz = [];

    public function mount(RolePermissionService $service): void
    {
        Gate::authorize('roles.gestionar');

        $this->matriz = $service->matriz();
    }

    public function alternar(string $rol, string $permiso, RolePermissionService $service): void
    {
        Gate::authorize('roles.gestionar');

        $role = Role::query()->where('name', $rol)->firstOrFail();

        if (in_array($permiso, $this->matriz[$rol] ?? [], true)) {
            $service->revocar($role, $permiso);
            $this->matriz[$rol] = array_values(array_diff($this->matriz[$rol], [$permiso]));
        } else {
            $service->otorgar($role, $permiso);
            $this->matriz[$rol][] = $permiso;
        }
    }

    public function with(RolePermissionService $service): array
    {
        $permisos = $service->permisos()->pluck('name');

        return [
            'roles' => $service->roles(),
            'gruposPermisos' => $permisos->groupBy(fn (string $permiso) => explode('.', $permiso)[0]),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Roles y permisos</h1>
        <p class="mt-1 text-sm text-ink-dim">Activa o desactiva permisos por rol. Los cambios aplican de inmediato.</p>
    </x-slot>

    <div class="overflow-x-auto rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="sticky left-0 bg-surface-2 px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">
                        Permiso
                    </th>
                    @foreach ($roles as $role)
                        <th class="px-3 py-3 text-center font-mono text-xs uppercase tracking-wide text-ink-faint">
                            {{ ucfirst($role->name) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach ($gruposPermisos as $grupo => $permisos)
                    <tr class="bg-surface-2/50">
                        <td colspan="{{ $roles->count() + 1 }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                            {{ str_replace('_', ' ', $grupo) }}
                        </td>
                    </tr>
                    @foreach ($permisos as $permiso)
                        <tr wire:key="permiso-{{ $permiso }}">
                            <td class="sticky left-0 bg-surface px-4 py-2 font-mono text-ink-dim">{{ $permiso }}</td>
                            @foreach ($roles as $role)
                                <td class="px-3 py-2 text-center">
                                    <button
                                        wire:click="alternar('{{ $role->name }}', '{{ $permiso }}')"
                                        wire:loading.attr="disabled"
                                        @class([
                                            'inline-flex h-5 w-5 items-center justify-center rounded border transition',
                                            'border-accent bg-accent text-white' => in_array($permiso, $matriz[$role->name] ?? []),
                                            'border-border bg-surface hover:border-accent' => ! in_array($permiso, $matriz[$role->name] ?? []),
                                        ])
                                        aria-label="Alternar {{ $permiso }} para {{ $role->name }}"
                                    >
                                        @if (in_array($permiso, $matriz[$role->name] ?? []))
                                            <x-heroicon-o-check class="h-3.5 w-3.5" />
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
