<?php

use App\Models\User;
use App\Modules\Docentes\Models\Contrato;
use App\Modules\Docentes\Services\ContratoService;
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
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public User $usuario;

    public string $name = '';

    public string $email = '';

    public string $dni = '';

    public string $phone = '';

    public string $estado = '';

    public string $rol = '';

    // Contrato de este usuario (punto 10 del backlog) -- ver
    // resources/views/components/usuarios/contratos-usuario.blade.php.
    public bool $mostrarFormContrato = false;

    public ?int $contratoId = null;

    public string $tipo = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $monto = '';

    public string $observaciones = '';

    public $documento = null;

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

    public function abrirFormContratoCrear(): void
    {
        Gate::authorize('contratos.gestionar');

        $this->reset(['contratoId', 'tipo', 'fechaFin', 'monto', 'observaciones', 'documento']);
        $this->fechaInicio = now()->format('Y-m-d');
        $this->resetValidation();
        $this->mostrarFormContrato = true;
    }

    public function abrirFormContratoEditar(int $contratoId): void
    {
        Gate::authorize('contratos.gestionar');

        $contrato = Contrato::query()->where('user_id', $this->usuario->id)->findOrFail($contratoId);

        $this->contratoId = $contrato->id;
        $this->tipo = $contrato->tipo;
        $this->fechaInicio = $contrato->fecha_inicio->format('Y-m-d');
        $this->fechaFin = $contrato->fecha_fin?->format('Y-m-d') ?? '';
        $this->monto = $contrato->monto !== null ? (string) $contrato->monto : '';
        $this->observaciones = $contrato->observaciones ?? '';
        $this->documento = null;
        $this->resetValidation();
        $this->mostrarFormContrato = true;
    }

    public function cerrarFormContrato(): void
    {
        $this->mostrarFormContrato = false;
    }

    public function guardarContrato(ContratoService $service): void
    {
        Gate::authorize('contratos.gestionar');

        $this->validate([
            'tipo' => 'required|string|max:150',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'nullable|date|after:fechaInicio',
            'monto' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $datos = [
            'tipo' => $this->tipo,
            'fechaInicio' => $this->fechaInicio,
            'fechaFin' => $this->fechaFin !== '' ? $this->fechaFin : null,
            'monto' => $this->monto !== '' ? (float) $this->monto : null,
            'observaciones' => $this->observaciones !== '' ? $this->observaciones : null,
        ];

        if ($this->contratoId === null) {
            $service->registrar($this->usuario, $datos, $this->documento, auth()->id());
            session()->flash('status', 'Contrato registrado correctamente.');
        } else {
            $service->actualizar(Contrato::query()->where('user_id', $this->usuario->id)->findOrFail($this->contratoId), $datos, $this->documento);
            session()->flash('status', 'Contrato actualizado correctamente.');
        }

        $this->mostrarFormContrato = false;
    }

    public function eliminarContrato(int $contratoId, ContratoService $service): void
    {
        Gate::authorize('contratos.gestionar');

        $service->eliminar(Contrato::query()->where('user_id', $this->usuario->id)->findOrFail($contratoId));

        session()->flash('status', 'Contrato eliminado.');
    }

    public function with(SessionControlService $sessions, AuditService $audit, ContratoService $contratos): array
    {
        $puedeVerContratos = Gate::allows('contratos.ver') && $this->usuario->esPersonalInstitucional();

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
            'puedeVerContratos' => $puedeVerContratos,
            'puedeGestionarContratos' => $puedeVerContratos && Gate::allows('contratos.gestionar'),
            'contratos' => $puedeVerContratos ? $contratos->deUsuario($this->usuario) : collect(),
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
        :puede-ver-contratos="$puedeVerContratos"
        :puede-gestionar-contratos="$puedeGestionarContratos"
        :contratos="$contratos"
        :contrato-id="$contratoId"
        :mostrar-form-contrato="$mostrarFormContrato"
        :tipo="$tipo"
        :fecha-inicio="$fechaInicio"
        :fecha-fin="$fechaFin"
        :monto="$monto"
        :observaciones="$observaciones"
    />
</div>
