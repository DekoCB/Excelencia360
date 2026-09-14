<?php

use App\Models\User;
use App\Modules\Identidad\DTOs\ActualizarUsuarioData;
use App\Modules\Identidad\Services\AuditService;
use App\Modules\Identidad\Services\SessionControlService;
use App\Modules\Identidad\Services\UserManagementService;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Modal "Ver" de usuarios/index.blade.php, aislado en su propio componente
 * para que abrirlo no dispare una re-consulta de la lista completa.
 */
new class extends Component
{
    public ?int $usuarioId = null;

    public string $name = '';

    public string $email = '';

    public string $dni = '';

    public string $phone = '';

    public string $estado = '';

    public string $rol = '';

    #[On('ver-usuario')]
    public function abrir(int $usuarioId): void
    {
        Gate::authorize('usuarios.ver');

        $usuario = User::query()->findOrFail($usuarioId);

        $this->usuarioId = $usuarioId;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->dni = (string) $usuario->dni;
        $this->phone = (string) $usuario->phone;
        $this->estado = $usuario->estado->value;
        $this->rol = $usuario->roles->first()?->name ?? '';
    }

    public function guardarDatos(UserManagementService $service): void
    {
        $usuario = $this->usuario();

        Gate::authorize('usuarios.editar', $usuario);

        $this->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'dni' => 'required|string|min:8|max:12',
            'phone' => 'nullable|string',
            'estado' => 'required|string|in:activo,inactivo,suspendido',
        ]);

        if (! $service->emailDisponible($this->email, $usuario->id)) {
            $this->addError('email', 'Ya existe otro usuario con este correo.');

            return;
        }

        if (! $service->dniDisponible($this->dni, $usuario->id)) {
            $this->addError('dni', 'Ya existe otro usuario con este documento.');

            return;
        }

        $service->actualizar($usuario, new ActualizarUsuarioData(
            name: $this->name,
            email: $this->email,
            dni: new Dni($this->dni),
            phone: $this->phone !== '' ? new Telefono($this->phone) : null,
            estado: EstadoUsuarioEnum::from($this->estado),
        ));

        session()->flash('status', 'Datos actualizados correctamente.');
    }

    public function cambiarRol(UserManagementService $service): void
    {
        $usuario = $this->usuario();

        Gate::authorize('assignRoles', $usuario);

        $this->validate([
            'rol' => 'required|string|in:'.implode(',', array_column(RolEnum::cases(), 'value')),
        ]);

        $service->cambiarRol($usuario, $this->rol);

        session()->flash('status', 'Rol actualizado correctamente.');
    }

    public function revocarSesion(string $sesionId, SessionControlService $service): void
    {
        Gate::authorize('manageSessions', $this->usuario());

        $service->revocar($sesionId);

        session()->flash('status', 'Sesión cerrada.');
    }

    private function usuario(): User
    {
        return User::query()->findOrFail($this->usuarioId);
    }

    public function with(SessionControlService $sessions, AuditService $audit): array
    {
        $usuario = $this->usuarioId ? User::query()->find($this->usuarioId) : null;

        return [
            'usuario' => $usuario,
            'rolesDisponibles' => RolEnum::cases(),
            'puedeGestionarRoles' => $usuario && Gate::allows('assignRoles', $usuario),
            'puedeGestionarSesiones' => $usuario && Gate::allows('manageSessions', $usuario),
            'sesiones' => $usuario && Gate::allows('manageSessions', $usuario)
                ? $sessions->sesionesDe($usuario, session()->getId())
                : collect(),
            'puedeVerAuditoria' => Gate::allows('auditoria.ver'),
            'historial' => $usuario && Gate::allows('auditoria.ver')
                ? $audit->historialDe($usuario)->take(10)
                : collect(),
        ];
    }
}; ?>

<div>
    <x-modal name="ver-usuario" :tv="true" max-width="2xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 class="font-display text-lg text-ink">{{ $usuario?->name ?? 'Usuario' }}</h2>
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="max-h-[75vh] overflow-y-auto p-6" wire:loading.class="opacity-50">
            @if ($usuario)
                <x-usuarios.detalle-usuario
                    :usuario="$usuario"
                    :roles-disponibles="$rolesDisponibles"
                    :puede-gestionar-roles="$puedeGestionarRoles"
                    :puede-gestionar-sesiones="$puedeGestionarSesiones"
                    :sesiones="$sesiones"
                    :puede-ver-auditoria="$puedeVerAuditoria"
                    :historial="$historial"
                />
            @else
                <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
            @endif
        </div>
    </x-modal>
</div>
