<?php

use App\Models\User;
use App\Modules\AsistenciaGeneral\Models\AsistenciaGeneral;
use App\Modules\AsistenciaGeneral\Services\AsistenciaGeneralService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * Los únicos tipos de "persona" válidos para registrar -- evita que un
     * wire:click manipulado pase una clase arbitraria a {@see registrarIngreso}.
     *
     * @var list<class-string>
     */
    private const TIPOS_PERSONA_VALIDOS = [Estudiante::class, User::class, Personal::class];

    public string $busqueda = '';

    public string $turnoElegido = '';

    /**
     * Checkbox del registro manual (buscador, no QR) -- deja constancia
     * de que se registró a mano porque la persona olvidó su fotocheck.
     */
    public bool $olvidoFotocheck = false;

    /**
     * Resultado del último escaneo/registro, para la tarjeta de
     * confirmación con sonido + animación (mismo patrón que
     * asistencia/show.blade.php).
     *
     * @var array{nombre: string, accion: 'ingreso'|'salida', hora: string}|null
     */
    public ?array $ultimoRegistro = null;

    public function mount(): void
    {
        abort_unless(
            Auth::user()->hasAnyPermission(['asistencia_general.ver', 'asistencia_general.registrar', 'asistencia_general.registrar_propio']),
            403,
        );
    }

    /**
     * Autoservicio para quien solo tiene asistencia_general.registrar_propio
     * (hoy, Docente): marca su propio ingreso/salida, sin ver ni poder
     * tocar el registro de nadie más -- a diferencia de
     * registrarIngreso()/registrarSalida(), que son para el panel
     * administrativo completo.
     */
    public function registrarMiAsistencia(AsistenciaGeneralService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_general.registrar_propio'), 403);

        $persona = Auth::user();
        $registroAbierto = $service->registroAbiertoDe($persona);

        if ($registroAbierto !== null) {
            $service->registrarSalida($registroAbierto);
            $this->confirmar($persona, 'salida');
            session()->flash('status', 'Salida registrada.');

            return;
        }

        $this->validate([
            'turnoElegido' => ['required', Rule::in(array_column(TurnoDiaEnum::cases(), 'value'))],
        ], [], ['turnoElegido' => 'turno']);

        $service->registrarIngreso($persona, TurnoDiaEnum::from($this->turnoElegido), Auth::user());
        $this->confirmar($persona, 'ingreso');
        session()->flash('status', 'Ingreso registrado.');
    }

    public function registrarIngreso(string $personaType, int $personaId, AsistenciaGeneralService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_general.registrar'), 403);
        abort_unless(in_array($personaType, self::TIPOS_PERSONA_VALIDOS, true), 403);

        $this->validate([
            'turnoElegido' => ['required', Rule::in(array_column(TurnoDiaEnum::cases(), 'value'))],
        ], [], ['turnoElegido' => 'turno']);

        /** @var \Illuminate\Database\Eloquent\Model $persona */
        $persona = $personaType::query()->findOrFail($personaId);

        $service->registrarIngreso(
            $persona,
            TurnoDiaEnum::from($this->turnoElegido),
            Auth::user(),
            $this->olvidoFotocheck ? 'Olvidó su fotocheck: registrado a mano.' : null,
        );

        $this->busqueda = '';
        $this->olvidoFotocheck = false;
        $this->confirmar($persona, 'ingreso');
        session()->flash('status', 'Ingreso registrado.');
    }

    public function registrarSalida(int $registroId, AsistenciaGeneralService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_general.registrar'), 403);

        $registro = AsistenciaGeneral::query()->findOrFail($registroId);

        $service->registrarSalida($registro);

        $this->confirmar($registro->persona, 'salida');
        session()->flash('status', 'Salida registrada.');
    }

    /**
     * El docente/estudiante escanea su QR de asistencia (mismo carnet que
     * ya usan para la asistencia por curso) para marcar su ingreso o
     * salida del Instituto -- si ya tiene un ingreso abierto, el mismo
     * escaneo lo cierra en vez de pedir un segundo código para la salida.
     */
    public function escanearQr(string $codigo, AsistenciaGeneralService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_general.registrar'), 403);

        session()->forget('qr_error');
        $this->ultimoRegistro = null;

        $persona = $service->personaParaQrToken($codigo);

        if ($persona === null) {
            session()->flash('qr_error', 'Código QR no reconocido.');

            return;
        }

        $registroAbierto = $service->registroAbiertoDe($persona);

        if ($registroAbierto !== null) {
            $service->registrarSalida($registroAbierto);
            $this->confirmar($persona, 'salida');

            return;
        }

        $this->validate([
            'turnoElegido' => ['required', Rule::in(array_column(TurnoDiaEnum::cases(), 'value'))],
        ], [], ['turnoElegido' => 'turno']);

        $service->registrarIngreso($persona, TurnoDiaEnum::from($this->turnoElegido), Auth::user());
        $this->confirmar($persona, 'ingreso');
    }

    private function confirmar(Estudiante|User|Personal $persona, string $accion): void
    {
        $this->ultimoRegistro = [
            'nombre' => method_exists($persona, 'nombreCompleto') ? $persona->nombreCompleto() : $persona->name,
            'accion' => $accion,
            'hora' => now()->format('H:i'),
        ];

        $this->dispatch('asistencia-confirmada');
    }

    public function with(AsistenciaGeneralService $service): array
    {
        $user = Auth::user();
        $puedeAdmin = $user->hasAnyPermission(['asistencia_general.ver', 'asistencia_general.registrar']);
        $soloPropio = ! $puedeAdmin && $user->hasPermissionTo('asistencia_general.registrar_propio');

        if ($soloPropio) {
            return [
                'puedeRegistrar' => false,
                'soloPropio' => true,
                'resultados' => collect(),
                'turnosDia' => TurnoDiaEnum::cases(),
                'historialDelDia' => collect(),
                'miRegistroAbierto' => $service->registroAbiertoDe($user),
                'miHistorialHoy' => $service->historialDelDia()->filter(
                    fn (AsistenciaGeneral $registro) => $registro->persona_type === User::class && $registro->persona_id === $user->id,
                ),
            ];
        }

        $resultados = $this->busqueda !== '' ? $service->buscarPersona($this->busqueda) : collect();

        $resultados = $resultados->map(function (array $resultado) use ($service) {
            $resultado['registroAbierto'] = $service->registroAbiertoDe($resultado['persona']);

            return $resultado;
        });

        return [
            'puedeRegistrar' => $user->hasPermissionTo('asistencia_general.registrar'),
            'soloPropio' => false,
            'resultados' => $resultados,
            'turnosDia' => TurnoDiaEnum::cases(),
            'historialDelDia' => $puedeAdmin ? $service->historialDelDia() : collect(),
            'miRegistroAbierto' => null,
            'miHistorialHoy' => collect(),
        ];
    }
}; ?>

<div x-data="{ confirmado: false }" x-on:asistencia-confirmada.window="confirmado = true; window.sonidoConfirmacion?.(); setTimeout(() => confirmado = false, 2500)">
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Asistencia general</h1>
        <p class="mt-1 text-sm text-ink-dim">Ingreso y salida del Instituto -- estudiantes y personal, por turno.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if (session('qr_error'))
        <p class="mb-4 rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">{{ session('qr_error') }}</p>
    @endif

    @if ($soloPropio)
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm p-4">
            <div class="mb-4">
                <x-input-label for="turnoElegido" value="Turno" />
                <x-select-input
                    wire:model="turnoElegido"
                    id="turnoElegido"
                    class="mt-1 block w-full sm:w-64"
                    placeholder="Selecciona…"
                    :options="collect($turnosDia)->mapWithKeys(fn ($turno) => [$turno->value => $turno->label()])"
                />
                <x-input-error :messages="$errors->get('turnoElegido')" class="mt-1" />
            </div>

            @if ($ultimoRegistro)
                <div class="relative mb-4 overflow-hidden rounded-md border border-accent/30 bg-accent-soft px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-display text-base text-ink">{{ $ultimoRegistro['nombre'] }}</p>
                        <x-badge :variant="$ultimoRegistro['accion'] === 'ingreso' ? 'ok' : 'warn'">
                            {{ $ultimoRegistro['accion'] === 'ingreso' ? 'Ingreso' : 'Salida' }}
                        </x-badge>
                    </div>
                    <p class="mt-1 text-xs text-ink-dim">Hora {{ $ultimoRegistro['hora'] }}</p>

                    <div
                        x-show="confirmado"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-50"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="ease-in duration-300"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-50"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center gap-2 bg-ok/90 text-white"
                    >
                        <x-heroicon-o-check-circle class="h-8 w-8" />
                        <span class="font-display text-xl">Confirmado</span>
                    </div>
                </div>
            @endif

            @if ($miRegistroAbierto)
                <p class="mb-3 text-sm text-ink-dim">
                    Entraste a las {{ $miRegistroAbierto->hora_ingreso->format('H:i') }}
                    en el turno de {{ $miRegistroAbierto->turno->label() }}.
                </p>
                <x-primary-button type="button" wire:click="registrarMiAsistencia">Marcar mi salida</x-primary-button>
            @else
                <x-primary-button type="button" wire:click="registrarMiAsistencia">Marcar mi ingreso</x-primary-button>
            @endif
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <h2 class="font-display text-sm text-ink">Mis registros de hoy</h2>
            </div>
            <div class="divide-y divide-border">
                @forelse ($miHistorialHoy as $registro)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <p class="text-xs text-ink-faint">
                            Turno de {{ $registro->turno->label() }} · Ingreso {{ $registro->hora_ingreso->format('H:i') }}
                            @if ($registro->hora_salida)
                                · Salida {{ $registro->hora_salida->format('H:i') }}
                            @endif
                        </p>
                        <x-badge :variant="$registro->estaAbierta() ? 'warn' : 'ok'">
                            {{ $registro->estaAbierta() ? 'Dentro' : 'Salió' }}
                        </x-badge>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no marcaste tu asistencia hoy.</p>
                @endforelse
            </div>
        </div>
    @else
    @if ($puedeRegistrar)
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm p-4">
            <div class="mb-4">
                <x-input-label for="turnoElegido" value="Turno" />
                <x-select-input
                    wire:model="turnoElegido"
                    id="turnoElegido"
                    class="mt-1 block w-full sm:w-64"
                    placeholder="Selecciona…"
                    :options="collect($turnosDia)->mapWithKeys(fn ($turno) => [$turno->value => $turno->label()])"
                />
                <x-input-error :messages="$errors->get('turnoElegido')" class="mt-1" />
            </div>

            @if ($ultimoRegistro)
                <div class="relative mb-4 overflow-hidden rounded-md border border-accent/30 bg-accent-soft px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-display text-base text-ink">{{ $ultimoRegistro['nombre'] }}</p>
                        <x-badge :variant="$ultimoRegistro['accion'] === 'ingreso' ? 'ok' : 'warn'">
                            {{ $ultimoRegistro['accion'] === 'ingreso' ? 'Ingreso' : 'Salida' }}
                        </x-badge>
                    </div>
                    <p class="mt-1 text-xs text-ink-dim">Hora {{ $ultimoRegistro['hora'] }}</p>

                    <div
                        x-show="confirmado"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-50"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="ease-in duration-300"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-50"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center gap-2 bg-ok/90 text-white"
                    >
                        <x-heroicon-o-check-circle class="h-8 w-8" />
                        <span class="font-display text-xl">Confirmado</span>
                    </div>
                </div>
            @endif

            <div class="mb-4">
                <x-qr-scanner metodo="escanearQr" />
            </div>

            <div>
                <x-input-label for="busqueda" value="O buscar por nombre o DNI" />
                <x-text-input wire:model.live.debounce.300ms="busqueda" id="busqueda" type="text" class="mt-1 block w-full" placeholder="Ej. Juan Pérez o 12345678" />
            </div>

            <label class="mt-2 flex items-center gap-1.5 text-xs text-ink-faint">
                <input type="checkbox" wire:model="olvidoFotocheck" class="rounded border-border text-accent focus:ring-accent">
                Olvidó su fotocheck (registro manual)
            </label>

            @if ($busqueda !== '')
                <div class="mt-4 divide-y divide-border rounded-lg border border-border">
                    @forelse ($resultados as $resultado)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                            <div>
                                <p class="text-ink">{{ $resultado['nombre'] }}</p>
                                <p class="text-xs text-ink-faint">{{ $resultado['tipo'] }} · DNI {{ $resultado['dni'] }}</p>
                            </div>

                            @if ($resultado['registroAbierto'])
                                <div class="flex items-center gap-2">
                                    <x-badge variant="warn">Dentro desde {{ $resultado['registroAbierto']->hora_ingreso->format('H:i') }}</x-badge>
                                    <x-secondary-button type="button" wire:click="registrarSalida({{ $resultado['registroAbierto']->id }})">
                                        Marcar salida
                                    </x-secondary-button>
                                </div>
                            @else
                                <x-primary-button
                                    type="button"
                                    wire:click="registrarIngreso('{{ $resultado['persona']::class }}', {{ $resultado['persona']->id }})"
                                >
                                    Marcar ingreso
                                </x-primary-button>
                            @endif
                        </div>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-ink-faint">Sin resultados para «{{ $busqueda }}».</p>
                    @endforelse
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm">
        <div class="border-b border-border px-4 py-3">
            <h2 class="font-display text-sm text-ink">Hoy</h2>
        </div>
        <div class="divide-y divide-border">
            @forelse ($historialDelDia as $registro)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $registro->nombrePersona() }}</p>
                        <p class="text-xs text-ink-faint">
                            Turno de {{ $registro->turno->label() }} · Ingreso {{ $registro->hora_ingreso->format('H:i') }}
                            @if ($registro->hora_salida)
                                · Salida {{ $registro->hora_salida->format('H:i') }}
                            @endif
                            @if ($registro->registradoPor)
                                · registrado por {{ $registro->registradoPor->name }}
                            @endif
                        </p>
                        @if ($registro->observacion)
                            <p class="mt-0.5 text-xs text-warn">{{ $registro->observacion }}</p>
                        @endif
                    </div>
                    <x-badge :variant="$registro->estaAbierta() ? 'warn' : 'ok'">
                        {{ $registro->estaAbierta() ? 'Dentro' : 'Salió' }}
                    </x-badge>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay registros hoy.</p>
            @endforelse
        </div>
    </div>
    @endif
</div>
