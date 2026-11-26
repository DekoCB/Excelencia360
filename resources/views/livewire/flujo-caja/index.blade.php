<?php

use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Services\FlujoCajaService;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Shared\Enums\TurnoDiaEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $vista = 'mensual';

    public string $mes = '';

    public string $fecha = '';

    public string $usuarioId = '';

    public bool $mostrarModal = false;

    public string $categoria = '';

    public string $descripcion = '';

    public string $monto = '';

    public string $metodo = '';

    public string $fechaEgreso = '';

    public $comprobante = null;

    public bool $mostrarModalAbrirTurno = false;

    public bool $mostrarModalCerrarTurno = false;

    public string $montoInicial = '';

    public string $turnoNuevo = '';

    public string $montoFinal = '';

    public bool $verTurnosCerrados = false;

    public function mount(): void
    {
        Gate::authorize('flujo_caja.ver');
        $this->mes = now()->format('Y-m');
        $this->fecha = now()->format('Y-m-d');
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->subMonth()->format('Y-m');
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->addMonth()->format('Y-m');
    }

    public function diaAnterior(): void
    {
        $this->fecha = Carbon::parse($this->fecha)->subDay()->format('Y-m-d');
    }

    public function diaSiguiente(): void
    {
        $this->fecha = Carbon::parse($this->fecha)->addDay()->format('Y-m-d');
    }

    public function abrirModalTurno(): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->resetValidation();
        $this->montoInicial = '';
        $this->turnoNuevo = '';
        $this->mostrarModalAbrirTurno = true;
    }

    public function abrirTurno(FlujoCajaService $service): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->validate([
            'montoInicial' => 'required|numeric|min:0',
            'turnoNuevo' => ['required', Rule::in(array_column(TurnoDiaEnum::cases(), 'value'))],
        ]);

        $service->abrirTurno(Auth::user(), (float) $this->montoInicial, TurnoDiaEnum::from($this->turnoNuevo));

        $this->mostrarModalAbrirTurno = false;
        session()->flash('status', 'Turno de caja abierto.');
    }

    public function abrirModalCerrarTurno(): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->resetValidation();
        $this->montoFinal = '';
        $this->mostrarModalCerrarTurno = true;
    }

    public function cerrarTurno(FlujoCajaService $service): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->validate(['montoFinal' => 'required|numeric|min:0']);

        $turno = $service->turnoAbiertoDe(Auth::user());
        abort_if($turno === null, 404);

        $service->cerrarTurno($turno, (float) $this->montoFinal);

        $this->mostrarModalCerrarTurno = false;
        session()->flash('status', 'Turno de caja cerrado.');
    }

    public function abrirModal(): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->resetValidation();
        $this->reset(['categoria', 'descripcion', 'monto', 'metodo', 'fechaEgreso', 'comprobante']);
        $this->fechaEgreso = now()->format('Y-m-d');
        $this->mostrarModal = true;
    }

    public function registrarEgreso(FlujoCajaService $service): void
    {
        Gate::authorize('flujo_caja.gestionar');

        $this->validate([
            'categoria' => 'required|string|in:'.implode(',', array_column(CategoriaEgresoEnum::cases(), 'value')),
            'descripcion' => 'nullable|string|max:500',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|string|in:'.implode(',', array_column(MetodoPagoEnum::seleccionables(), 'value')),
            'fechaEgreso' => 'required|date',
            'comprobante' => 'nullable|file|max:5120',
        ]);

        $service->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::from($this->categoria),
            'descripcion' => $this->descripcion !== '' ? $this->descripcion : null,
            'monto' => (float) $this->monto,
            'metodo' => MetodoPagoEnum::from($this->metodo),
            'fecha_egreso' => $this->fechaEgreso,
        ], $this->comprobante, Auth::id());

        $this->mostrarModal = false;
        session()->flash('status', 'Egreso registrado correctamente.');
    }

    /**
     * @return array{inicio: Carbon, fin: Carbon, mesLabel: string, ingresos: float, egresos: float, saldoNeto: float, movimientos: Collection}
     */
    private function datosDelPeriodo(FlujoCajaService $service): array
    {
        if ($this->vista === 'diario') {
            $inicio = Carbon::parse($this->fecha)->startOfDay();
            $fin = $inicio->copy()->endOfDay();
            $mesLabel = ucfirst($inicio->translatedFormat('d \d\e F \d\e Y'));
        } else {
            $inicio = Carbon::parse("{$this->mes}-01")->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();
            $mesLabel = ucfirst($inicio->translatedFormat('F Y'));
        }

        $usuarioId = $this->usuarioId !== '' ? (int) $this->usuarioId : null;

        $ingresos = $service->ingresosDelPeriodo($inicio, $fin, $usuarioId);
        $egresos = $service->egresosDelPeriodo($inicio, $fin, $usuarioId);

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'mesLabel' => $mesLabel,
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'saldoNeto' => $ingresos - $egresos,
            'movimientos' => $service->movimientosDelPeriodo($inicio, $fin, $usuarioId),
        ];
    }

    public function exportarPdf(FlujoCajaService $service)
    {
        abort_unless(Auth::user()->hasPermissionTo('flujo_caja.ver'), 403);

        $datos = $this->datosDelPeriodo($service);
        $nombreArchivo = 'flujo-caja-'.$datos['inicio']->format('Y-m').'.pdf';

        // Ver reportes/index.blade.php: Pdf::loadView(...)->download() no lo
        // reconoce Livewire como descarga (devuelve un Response plano, no un
        // StreamedResponse/BinaryFileResponse) -- streamDownload() sí.
        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.flujo-caja', $datos)->output()),
            $nombreArchivo,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function with(FlujoCajaService $service): array
    {
        $datos = $this->datosDelPeriodo($service);

        [$labelsIngresos, $datosIngresos] = $service->ingresosPorMes(6);
        [$labelsEgresos, $datosEgresos] = $service->egresosPorMes(6);

        $puedeGestionar = Auth::user()->hasPermissionTo('flujo_caja.gestionar');
        $turnoAbierto = $puedeGestionar ? $service->turnoAbiertoDe(Auth::user()) : null;
        $usuarioIdFiltro = $this->usuarioId !== '' ? (int) $this->usuarioId : null;

        return [
            ...$datos,
            'categorias' => CategoriaEgresoEnum::cases(),
            'metodos' => MetodoPagoEnum::seleccionables(),
            'turnosDia' => TurnoDiaEnum::cases(),
            'labelsIngresos' => $labelsIngresos,
            'datosIngresos' => $datosIngresos,
            'labelsEgresos' => $labelsEgresos,
            'datosEgresos' => $datosEgresos,
            'puedeGestionar' => $puedeGestionar,
            'usuarios' => $service->usuariosConMovimientos(),
            'turnoAbierto' => $turnoAbierto,
            'movimientosDelTurno' => $turnoAbierto ? $service->movimientosDelTurno($turnoAbierto) : null,
            'montoCalculadoTurno' => $turnoAbierto ? $service->montoCalculadoDelTurno($turnoAbierto) : null,
            'turnosCerrados' => ($puedeGestionar && $this->verTurnosCerrados)
                ? $service->turnosCerrados($usuarioIdFiltro)->map(function ($turnoCerrado) use ($service) {
                    $montoCalculado = $service->montoCalculadoDelTurno($turnoCerrado);

                    return [
                        'turno' => $turnoCerrado,
                        'montoCalculado' => $montoCalculado,
                        'diferencia' => (float) $turnoCerrado->monto_final_declarado - $montoCalculado,
                    ];
                })
                : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Flujo de caja</h1>
        <p class="mt-1 text-sm text-ink-dim">Ingresos (pagos aprobados) y egresos registrados a mano, mes a mes.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @can('flujo_caja.gestionar')
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm p-4">
            @if ($turnoAbierto)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-display text-sm text-ink">
                            Turno de {{ $turnoAbierto->turno?->label() ?? '—' }} abierto por {{ $turnoAbierto->usuario->name }}
                            desde {{ $turnoAbierto->hora_apertura->format('d/m/Y H:i') }}
                        </p>
                        <p class="mt-1 text-xs text-ink-faint">
                            Monto inicial S/ {{ number_format((float) $turnoAbierto->monto_inicial, 2) }}
                            · {{ $movimientosDelTurno->count() }} movimiento(s)
                            · debería haber S/ {{ number_format($montoCalculadoTurno, 2) }} en caja
                        </p>
                    </div>
                    <x-secondary-button type="button" wire:click="abrirModalCerrarTurno">Cerrar turno</x-secondary-button>
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-ink-dim">No tienes un turno de caja abierto.</p>
                    <x-primary-button type="button" wire:click="abrirModalTurno">Abrir turno</x-primary-button>
                </div>
            @endif
        </div>
    @endcan

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex overflow-hidden rounded-md border border-border">
                <button type="button" wire:click="$set('vista', 'mensual')" @class(['px-3 py-1.5 text-xs font-medium transition', 'bg-accent text-white' => $vista === 'mensual', 'text-ink-dim hover:bg-surface-2' => $vista !== 'mensual'])>Mensual</button>
                <button type="button" wire:click="$set('vista', 'diario')" @class(['px-3 py-1.5 text-xs font-medium transition', 'bg-accent text-white' => $vista === 'diario', 'text-ink-dim hover:bg-surface-2' => $vista !== 'diario'])>Diario</button>
            </div>

            @if ($vista === 'mensual')
                <button type="button" wire:click="mesAnterior" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </button>
                <p class="font-display text-lg capitalize text-ink">{{ $mesLabel }}</p>
                <button type="button" wire:click="mesSiguiente" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                    <x-heroicon-o-chevron-right class="h-4 w-4" />
                </button>
            @else
                <button type="button" wire:click="diaAnterior" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </button>
                <p class="font-display text-lg capitalize text-ink">{{ $mesLabel }}</p>
                <button type="button" wire:click="diaSiguiente" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                    <x-heroicon-o-chevron-right class="h-4 w-4" />
                </button>
            @endif

            @if ($usuarios->isNotEmpty())
                <x-select-input
                    wire:model.live="usuarioId"
                    class="text-sm"
                    :options="collect(['' => 'Todos los usuarios'])->merge($usuarios->mapWithKeys(fn ($usuario) => [(string) $usuario->id => $usuario->name]))"
                />
            @endif
        </div>

        <div class="flex gap-2">
            <x-secondary-button type="button" wire:click="exportarPdf" wire:loading.attr="disabled" wire:target="exportarPdf">
                Exportar PDF
            </x-secondary-button>

            @can('flujo_caja.gestionar')
                <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Registrar egreso
                </x-primary-button>
            @endcan
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Ingresos</p>
            <p class="mt-1 font-display text-2xl text-ok">S/ {{ number_format($ingresos, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Egresos</p>
            <p class="mt-1 font-display text-2xl text-danger">S/ {{ number_format($egresos, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Saldo neto</p>
            <p @class(['mt-1 font-display text-2xl', 'text-ok' => $saldoNeto >= 0, 'text-danger' => $saldoNeto < 0])>S/ {{ number_format($saldoNeto, 2) }}</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <h2 class="mb-3 text-sm font-semibold text-ink">Ingresos — últimos 6 meses</h2>
            <x-chart-canvas type="line" :labels="$labelsIngresos" :data="$datosIngresos" label="Ingresos (S/)" color="#16A34A" />
        </div>
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <h2 class="mb-3 text-sm font-semibold text-ink">Egresos — últimos 6 meses</h2>
            <x-chart-canvas type="line" :labels="$labelsEgresos" :data="$datosEgresos" label="Egresos (S/)" color="#DC2626" />
        </div>
    </div>

    @can('flujo_caja.gestionar')
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-border px-4 py-3">
                <h2 class="font-display text-sm text-ink">Turnos cerrados</h2>
                <button type="button" wire:click="$set('verTurnosCerrados', {{ $verTurnosCerrados ? 'false' : 'true' }})" class="text-xs font-medium text-accent hover:underline">
                    {{ $verTurnosCerrados ? 'Ocultar' : 'Ver turnos cerrados' }}
                </button>
            </div>
            @if ($verTurnosCerrados)
                <div class="divide-y divide-border">
                    @forelse ($turnosCerrados as $fila)
                        @php $turnoCerrado = $fila['turno']; @endphp
                        <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 text-sm">
                            <div>
                                <p class="text-ink">
                                    Turno de {{ $turnoCerrado->turno?->label() ?? '—' }} · {{ $turnoCerrado->usuario->name }}
                                </p>
                                <p class="text-xs text-ink-faint">
                                    {{ $turnoCerrado->hora_apertura->format('d/m/Y H:i') }} — {{ $turnoCerrado->hora_cierre->format('H:i') }}
                                    · apertura S/ {{ number_format((float) $turnoCerrado->monto_inicial, 2) }}
                                    · declarado S/ {{ number_format((float) $turnoCerrado->monto_final_declarado, 2) }}
                                    · calculado S/ {{ number_format($fila['montoCalculado'], 2) }}
                                </p>
                            </div>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => abs($fila['diferencia']) < 0.01,
                                'bg-danger/10 text-danger' => abs($fila['diferencia']) >= 0.01,
                            ])>
                                {{ abs($fila['diferencia']) < 0.01 ? 'Cuadrado' : ($fila['diferencia'] > 0 ? 'Sobra' : 'Falta').' S/ '.number_format(abs($fila['diferencia']), 2) }}
                            </span>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">Sin turnos cerrados todavía.</p>
                    @endforelse
                </div>
            @endif
        </div>
    @endcan

    <div class="rounded-2xl border border-border bg-surface shadow-sm">
        <div class="border-b border-border px-4 py-3">
            <h2 class="font-display text-sm text-ink">Movimientos de {{ $mesLabel }}</h2>
        </div>
        <div class="divide-y divide-border">
            @forelse ($movimientos as $movimiento)
                <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <div class="flex items-center gap-3">
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-ok/10 text-ok' => $movimiento['tipo'] === 'ingreso',
                            'bg-danger/10 text-danger' => $movimiento['tipo'] === 'egreso',
                        ])>
                            {{ $movimiento['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' }}
                        </span>
                        <div>
                            <p class="text-ink">{{ $movimiento['concepto'] }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $movimiento['fecha']->format('d/m/Y') }} · {{ $movimiento['metodo'] }}
                                @if ($movimiento['comprobanteUrl'])
                                    · <a href="{{ $movimiento['comprobanteUrl'] }}" target="_blank" class="font-medium text-accent hover:underline">Comprobante</a>
                                @endif
                            </p>
                        </div>
                    </div>
                    <p @class(['font-display', 'text-ok' => $movimiento['tipo'] === 'ingreso', 'text-danger' => $movimiento['tipo'] === 'egreso'])>
                        {{ $movimiento['tipo'] === 'ingreso' ? '+' : '−' }} S/ {{ number_format($movimiento['monto'], 2) }}
                    </p>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">Sin movimientos en {{ $mesLabel }}.</p>
            @endforelse
        </div>
    </div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">Registrar egreso</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label for="categoria" value="Categoría" />
                    <x-select-input
                        wire:model="categoria"
                        id="categoria"
                        class="mt-1 block w-full"
                        :options="collect($categorias)->mapWithKeys(fn ($categoriaOpcion) => [$categoriaOpcion->value => $categoriaOpcion->label()])"
                    />
                    <x-input-error :messages="$errors->get('categoria')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="descripcion" value="Descripción (opcional)" />
                    <textarea wire:model="descripcion" id="descripcion" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="monto" value="Monto (S/)" />
                        <x-text-input wire:model="monto" id="monto" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('monto')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="metodo" value="Método" />
                        <x-select-input
                            wire:model="metodo"
                            id="metodo"
                            class="mt-1 block w-full"
                            :options="collect($metodos)->mapWithKeys(fn ($metodoOpcion) => [$metodoOpcion->value => $metodoOpcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('metodo')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="fechaEgreso" value="Fecha" />
                    <x-date-input wire:model="fechaEgreso" id="fechaEgreso" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaEgreso')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="comprobante" value="Comprobante (opcional)" />
                    <input wire:model="comprobante" id="comprobante" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('comprobante')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="registrarEgreso">Registrar</x-primary-button>
            </div>
        </div>
    </div>

    <div
        x-show="$wire.mostrarModalAbrirTurno"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModalAbrirTurno"
            class="w-full max-w-sm rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">Abrir turno de caja</h2>
            <p class="mt-1 text-sm text-ink-dim">Indica el turno y cuánto dinero tienes en caja al empezar.</p>

            <div class="mt-4">
                <x-input-label for="turnoNuevo" value="Turno" />
                <x-select-input
                    wire:model="turnoNuevo"
                    id="turnoNuevo"
                    class="mt-1 block w-full"
                    placeholder="Selecciona…"
                    :options="collect($turnosDia)->mapWithKeys(fn ($turno) => [$turno->value => $turno->label()])"
                />
                <x-input-error :messages="$errors->get('turnoNuevo')" class="mt-1" />
            </div>

            <div class="mt-4">
                <x-input-label for="montoInicial" value="Monto inicial (S/)" />
                <x-text-input wire:model="montoInicial" id="montoInicial" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('montoInicial')" class="mt-1" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModalAbrirTurno', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="abrirTurno">Abrir turno</x-primary-button>
            </div>
        </div>
    </div>

    <div
        x-show="$wire.mostrarModalCerrarTurno"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModalCerrarTurno"
            class="w-full max-w-sm rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">Cerrar turno de caja</h2>
            @if ($turnoAbierto)
                <p class="mt-1 text-sm text-ink-dim">Según el sistema, debería haber <span class="font-semibold text-ink">S/ {{ number_format($montoCalculadoTurno, 2) }}</span> en caja.</p>
            @endif

            <div class="mt-4">
                <x-input-label for="montoFinal" value="Monto final en caja (S/)" />
                <x-text-input wire:model="montoFinal" id="montoFinal" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('montoFinal')" class="mt-1" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModalCerrarTurno', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="cerrarTurno">Cerrar turno</x-primary-button>
            </div>
        </div>
    </div>
</div>
