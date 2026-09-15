<?php

use App\Modules\Calendario\Enums\TipoEventoEnum;
use App\Modules\Calendario\Models\EventoCalendario;
use App\Modules\Calendario\Services\CalendarioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Vista única de calendario mensual. CalendarioService::itemsDelMes()
 * decide qué clases/evaluaciones corresponden a cada usuario según su rol
 * (igual que "Mis evaluaciones"); los eventos puntuales (reuniones, actos,
 * feriados) son visibles para todos, pero solo quien tiene
 * calendario.gestionar puede crearlos/editarlos/eliminarlos.
 */
new #[Layout('layouts.app')] class extends Component
{
    public string $mes = '';

    public bool $mostrarFormNuevo = false;

    public ?int $eventoEditandoId = null;

    public string $tipo = '';

    public string $titulo = '';

    public string $descripcion = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $horaInicio = '';

    public string $horaFin = '';

    public ?string $diaSeleccionado = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('calendario.ver'), 403);
        $this->mes = now()->format('Y-m');
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse($this->mes.'-01')->subMonthNoOverflow()->format('Y-m');
        $this->diaSeleccionado = null;
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse($this->mes.'-01')->addMonthNoOverflow()->format('Y-m');
        $this->diaSeleccionado = null;
    }

    public function irAHoy(): void
    {
        $this->mes = now()->format('Y-m');
        $this->diaSeleccionado = now()->toDateString();
    }

    public function seleccionarDia(string $fecha): void
    {
        $this->diaSeleccionado = $this->diaSeleccionado === $fecha ? null : $fecha;
    }

    public function abrirFormNuevo(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('calendario.gestionar'), 403);

        $this->reset(['tipo', 'titulo', 'descripcion', 'fechaFin', 'horaInicio', 'horaFin', 'eventoEditandoId']);
        $this->fechaInicio = $this->diaSeleccionado ?? now()->toDateString();
        $this->mostrarFormNuevo = true;
    }

    public function editarEvento(int $eventoId): void
    {
        abort_unless(Auth::user()->hasPermissionTo('calendario.gestionar'), 403);

        $evento = EventoCalendario::query()->findOrFail($eventoId);

        $this->eventoEditandoId = $evento->id;
        $this->tipo = $evento->tipo->value;
        $this->titulo = $evento->titulo;
        $this->descripcion = (string) $evento->descripcion;
        $this->fechaInicio = $evento->fecha_inicio->toDateString();
        $this->fechaFin = $evento->fecha_fin?->toDateString() ?? '';
        $this->horaInicio = (string) $evento->hora_inicio;
        $this->horaFin = (string) $evento->hora_fin;
        $this->mostrarFormNuevo = true;
    }

    public function cerrarForm(): void
    {
        $this->reset(['mostrarFormNuevo', 'eventoEditandoId', 'tipo', 'titulo', 'descripcion', 'fechaInicio', 'fechaFin', 'horaInicio', 'horaFin']);
        $this->resetErrorBag();
    }

    public function guardar(CalendarioService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('calendario.gestionar'), 403);

        $this->validate([
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoEventoEnum::cases(), 'value')),
            'titulo' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:2000',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'nullable|date|after_or_equal:fechaInicio',
            'horaInicio' => 'nullable|date_format:H:i',
            'horaFin' => 'nullable|date_format:H:i|after:horaInicio',
        ]);

        $tipo = TipoEventoEnum::from($this->tipo);
        $fechaInicio = Carbon::parse($this->fechaInicio);
        $fechaFin = $this->fechaFin !== '' ? Carbon::parse($this->fechaFin) : null;
        $horaInicio = $this->horaInicio !== '' ? $this->horaInicio : null;
        $horaFin = $this->horaFin !== '' ? $this->horaFin : null;
        $descripcion = $this->descripcion !== '' ? $this->descripcion : null;

        if ($this->eventoEditandoId !== null) {
            $evento = EventoCalendario::query()->findOrFail($this->eventoEditandoId);
            $service->actualizarEvento($evento, $tipo, $this->titulo, $descripcion, $fechaInicio, $fechaFin, $horaInicio, $horaFin);
        } else {
            $service->registrarEvento(Auth::user(), $tipo, $this->titulo, $descripcion, $fechaInicio, $fechaFin, $horaInicio, $horaFin);
        }

        $this->cerrarForm();
        session()->flash('status', 'Evento guardado.');
    }

    public function eliminar(int $eventoId, CalendarioService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('calendario.gestionar'), 403);

        $service->eliminarEvento(EventoCalendario::query()->findOrFail($eventoId));

        $this->cerrarForm();
        session()->flash('status', 'Evento eliminado.');
    }

    public function with(CalendarioService $service): array
    {
        $mesCarbon = Carbon::parse($this->mes.'-01');
        $items = $service->itemsDelMes(Auth::user(), $mesCarbon);
        $itemsPorDia = $items->groupBy(fn ($item) => $item->fecha->toDateString());

        $inicioCalendario = $mesCarbon->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $finCalendario = $mesCarbon->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);

        $dias = [];
        for ($fecha = $inicioCalendario->copy(); $fecha->lte($finCalendario); $fecha->addDay()) {
            $dias[] = $fecha->copy();
        }

        return [
            'puedeGestionar' => Auth::user()->hasPermissionTo('calendario.gestionar'),
            'mesCarbon' => $mesCarbon,
            'dias' => $dias,
            'itemsPorDia' => $itemsPorDia,
            'itemsDelDiaSeleccionado' => $this->diaSeleccionado !== null ? ($itemsPorDia->get($this->diaSeleccionado) ?? collect()) : collect(),
            'tipos' => TipoEventoEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Calendario académico</h1>
        <p class="mt-1 text-sm text-ink-dim">Clases, evaluaciones, reuniones y eventos institucionales en un solo lugar.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-secondary-button type="button" wire:click="mesAnterior">&larr;</x-secondary-button>
            <h2 class="min-w-[10rem] text-center font-display text-lg text-ink">{{ ucfirst($mesCarbon->translatedFormat('F Y')) }}</h2>
            <x-secondary-button type="button" wire:click="mesSiguiente">&rarr;</x-secondary-button>
            <x-secondary-button type="button" wire:click="irAHoy">Hoy</x-secondary-button>
        </div>

        @if ($puedeGestionar)
            <x-primary-button type="button" wire:click="abrirFormNuevo">+ Nuevo evento</x-primary-button>
        @endif
    </div>

    @if ($mostrarFormNuevo)
        <form wire:submit="guardar" class="mb-6 max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h3 class="font-display text-base text-ink">{{ $eventoEditandoId ? 'Editar evento' : 'Nuevo evento' }}</h3>

            <div>
                <x-input-label for="tipo" value="Tipo" />
                <x-select-input
                    wire:model="tipo"
                    id="tipo"
                    class="mt-1 block w-full"
                    :options="collect($tipos)->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                />
                <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="titulo" value="Título" />
                <x-text-input wire:model="titulo" id="titulo" class="mt-1 block w-full" placeholder="Ej. Reunión de coordinación docente" />
                <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="descripcion" value="Descripción (opcional)" />
                <textarea wire:model="descripcion" id="descripcion" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="fechaInicio" value="Fecha de inicio" />
                    <x-text-input wire:model="fechaInicio" id="fechaInicio" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaInicio')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="fechaFin" value="Fecha de fin (opcional)" />
                    <x-text-input wire:model="fechaFin" id="fechaFin" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaFin')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="horaInicio" value="Hora de inicio (opcional)" />
                    <x-text-input wire:model="horaInicio" id="horaInicio" type="time" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('horaInicio')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="horaFin" value="Hora de fin (opcional)" />
                    <x-text-input wire:model="horaFin" id="horaFin" type="time" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('horaFin')" class="mt-1" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" wire:click="cerrarForm">Cancelar</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="guardar">Guardar</x-primary-button>
            </div>
        </form>
    @endif

    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-2xl border border-border bg-border text-xs">
        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia)
            <div class="bg-surface-2 p-2 text-center font-semibold uppercase tracking-wide text-ink-faint">{{ $nombreDia }}</div>
        @endforeach

        @foreach ($dias as $dia)
            @php
                $itemsDelDia = $itemsPorDia->get($dia->toDateString(), collect());
                $esDelMes = $dia->month === $mesCarbon->month;
                $esHoy = $dia->isToday();
                $estaSeleccionado = $diaSeleccionado === $dia->toDateString();
            @endphp
            <button
                type="button"
                wire:click="seleccionarDia('{{ $dia->toDateString() }}')"
                class="flex min-h-[5.5rem] flex-col items-start gap-1 bg-surface p-1.5 text-left transition
                    {{ $esDelMes ? '' : 'opacity-40' }}
                    {{ $estaSeleccionado ? 'ring-2 ring-inset ring-accent' : '' }}"
            >
                <span class="text-[0.7rem] font-semibold {{ $esHoy ? 'rounded-full bg-accent px-1.5 py-0.5 text-white' : 'text-ink-dim' }}">{{ $dia->day }}</span>
                <div class="flex w-full flex-col gap-0.5">
                    @foreach ($itemsDelDia->take(2) as $item)
                        <span class="truncate rounded px-1 py-0.5 text-[0.65rem] leading-tight
                            {{ match($item->categoria->value) {
                                'clase' => 'bg-ink-faint/10 text-ink-dim',
                                'evaluacion' => 'bg-danger/10 text-danger',
                                default => 'bg-accent-soft text-accent',
                            } }}">{{ $item->titulo }}</span>
                    @endforeach
                    @if ($itemsDelDia->count() > 2)
                        <span class="text-[0.65rem] text-ink-faint">+{{ $itemsDelDia->count() - 2 }} más</span>
                    @endif
                </div>
            </button>
        @endforeach
    </div>

    @if ($diaSeleccionado)
        <div class="mt-6 rounded-2xl border border-border bg-surface shadow-sm p-5">
            <h3 class="font-display text-base text-ink">{{ Carbon::parse($diaSeleccionado)->translatedFormat('l d \d\e F') }}</h3>

            <div class="mt-3 space-y-3">
                @forelse ($itemsDelDiaSeleccionado as $item)
                    <div class="flex items-start justify-between gap-3 rounded-md border border-border p-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge variant="{{ $item->categoria->variantePildora() }}">{{ $item->categoria->label() }}</x-badge>
                                @if ($item->tipoEvento)
                                    <x-badge variant="{{ $item->tipoEvento->variantePildora() }}">{{ $item->tipoEvento->label() }}</x-badge>
                                @endif
                                <span class="text-sm font-semibold text-ink">{{ $item->titulo }}</span>
                            </div>
                            @if ($item->subtitulo)
                                <p class="mt-1 text-xs text-ink-dim">{{ $item->subtitulo }}</p>
                            @endif
                            @if ($item->horaInicio)
                                <p class="mt-1 text-xs text-ink-faint">{{ $item->horaInicio }}@if ($item->horaFin) – {{ $item->horaFin }} @endif</p>
                            @endif
                        </div>

                        @if ($puedeGestionar && $item->eventoId)
                            <div class="flex shrink-0 gap-2">
                                <button type="button" wire:click="editarEvento({{ $item->eventoId }})" class="text-xs font-medium text-accent hover:underline">Editar</button>
                                <button type="button" wire:click="eliminar({{ $item->eventoId }})" wire:confirm="¿Eliminar este evento?" class="text-xs font-medium text-danger-ink hover:underline">Eliminar</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-faint">Nada programado este día.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
