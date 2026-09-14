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
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public User $usuario;

    public string $name = '';

    public string $email = '';

    public string $dni = '';

    public string $phone = '';

    public string $estado = '';

    public string $rol = '';

    public function mount(User $usuario): void
    {
        Gate::authorize('usuarios.ver');

        $this->usuario = $usuario;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->dni = (string) $usuario->dni;
        $this->phone = (string) $usuario->phone;
        $this->estado = $usuario->estado->value;
        $this->rol = $usuario->roles->first()?->name ?? '';
    }

    public function guardarDatos(UserManagementService $service): void
    {
        Gate::authorize('usuarios.editar', $this->usuario);

        $this->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'dni' => 'required|string|min:8|max:12',
            'phone' => 'nullable|string',
            'estado' => 'required|string|in:activo,inactivo,suspendido',
        ]);

        if (! $service->emailDisponible($this->email, $this->usuario->id)) {
            $this->addError('email', 'Ya existe otro usuario con este correo.');

            return;
        }

        if (! $service->dniDisponible($this->dni, $this->usuario->id)) {
            $this->addError('dni', 'Ya existe otro usuario con este documento.');

            return;
        }

        $this->usuario = $service->actualizar($this->usuario, new ActualizarUsuarioData(
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
        Gate::authorize('assignRoles', $this->usuario);

        $this->validate([
            'rol' => 'required|string|in:'.implode(',', array_column(RolEnum::cases(), 'value')),
        ]);

        $service->cambiarRol($this->usuario, $this->rol);
        $this->usuario->refresh();

        session()->flash('status', 'Rol actualizado correctamente.');
    }

    public function revocarSesion(string $sesionId, SessionControlService $service): void
    {
        Gate::authorize('manageSessions', $this->usuario);

        $service->revocar($sesionId);

        session()->flash('status', 'Sesión cerrada.');
    }

    public function with(SessionControlService $sessions, AuditService $audit): array
    {
        return [
            'rolesDisponibles' => RolEnum::cases(),
            'puedeGestionarRoles' => Gate::allows('assignRoles', $this->usuario),
            'puedeGestionarSesiones' => Gate::allows('manageSessions', $this->usuario),
            'sesiones' => Gate::allows('manageSessions', $this->usuario)
                ? $sessions->sesionesDe($this->usuario, session()->getId())
                : collect(),
            'puedeVerAuditoria' => Gate::allows('auditoria.ver'),
            'historial' => Gate::allows('auditoria.ver')
                ? $audit->historialDe($this->usuario)->take(10)
                : collect(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('usuarios.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Usuarios</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $usuario->name }}</h1>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <x-usuarios.detalle-usuario
        :usuario="$usuario"
        :roles-disponibles="$rolesDisponibles"
        :puede-gestionar-roles="$puedeGestionarRoles"
        :puede-gestionar-sesiones="$puedeGestionarSesiones"
        :sesiones="$sesiones"
        :puede-ver-auditoria="$puedeVerAuditoria"
        :historial="$historial"
    />
</div>
