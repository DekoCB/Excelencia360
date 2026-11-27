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

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyPermission(['asistencia_general.ver', 'asistencia_general.registrar']), 403);
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

        $service->registrarIngreso($persona, TurnoDiaEnum::from($this->turnoElegido), Auth::user());

        $this->busqueda = '';
        session()->flash('status', 'Ingreso registrado.');
    }

    public function registrarSalida(int $registroId, AsistenciaGeneralService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('asistencia_general.registrar'), 403);

        $registro = AsistenciaGeneral::query()->findOrFail($registroId);

        $service->registrarSalida($registro);

        session()->flash('status', 'Salida registrada.');
    }

    public function with(AsistenciaGeneralService $service): array
    {
        $resultados = $this->busqueda !== '' ? $service->buscarPersona($this->busqueda) : collect();

        $resultados = $resultados->map(function (array $resultado) use ($service) {
            $resultado['registroAbierto'] = $service->registroAbiertoDe($resultado['persona']);

            return $resultado;
        });

        return [
            'puedeRegistrar' => Auth::user()->hasPermissionTo('asistencia_general.registrar'),
            'resultados' => $resultados,
            'turnosDia' => TurnoDiaEnum::cases(),
            'historialDelDia' => $service->historialDelDia(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Asistencia general</h1>
        <p class="mt-1 text-sm text-ink-dim">Ingreso y salida del Instituto -- estudiantes y personal, por turno.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($puedeRegistrar)
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm p-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto]">
                <div>
                    <x-input-label for="busqueda" value="Buscar por nombre o DNI" />
                    <x-text-input wire:model.live.debounce.300ms="busqueda" id="busqueda" type="text" class="mt-1 block w-full" placeholder="Ej. Juan Pérez o 12345678" autofocus />
                </div>
                <div>
                    <x-input-label for="turnoElegido" value="Turno" />
                    <x-select-input
                        wire:model="turnoElegido"
                        id="turnoElegido"
                        class="mt-1 block w-full"
                        placeholder="Selecciona…"
                        :options="collect($turnosDia)->mapWithKeys(fn ($turno) => [$turno->value => $turno->label()])"
                    />
                    <x-input-error :messages="$errors->get('turnoElegido')" class="mt-1" />
                </div>
            </div>

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
</div>
