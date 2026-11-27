<?php

use App\Modules\CajaChica\Services\CajaChicaService;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $mes = '';

    public bool $mostrarModalGasto = false;

    public string $categoria = '';

    public string $descripcion = '';

    public string $monto = '';

    public string $fechaGasto = '';

    public $comprobante = null;

    public bool $mostrarModalFondo = false;

    public string $montoFondo = '';

    public function mount(): void
    {
        Gate::authorize('caja_chica.ver');
        $this->mes = now()->format('Y-m');
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->subMonth()->format('Y-m');
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->addMonth()->format('Y-m');
    }

    public function abrirModalGasto(): void
    {
        Gate::authorize('caja_chica.gestionar');

        $this->resetValidation();
        $this->reset(['categoria', 'descripcion', 'monto', 'comprobante']);
        $this->fechaGasto = now()->format('Y-m-d');
        $this->mostrarModalGasto = true;
    }

    public function registrarGasto(CajaChicaService $service): void
    {
        Gate::authorize('caja_chica.gestionar');

        $this->validate([
            'categoria' => 'required|string|in:'.implode(',', array_column(CategoriaEgresoEnum::cases(), 'value')),
            'descripcion' => 'nullable|string|max:500',
            'monto' => 'required|numeric|min:0.01',
            'fechaGasto' => 'required|date',
            'comprobante' => 'nullable|file|max:5120',
        ]);

        $service->registrarGasto([
            'categoria' => CategoriaEgresoEnum::from($this->categoria),
            'descripcion' => $this->descripcion !== '' ? $this->descripcion : null,
            'monto' => (float) $this->monto,
            'fecha' => $this->fechaGasto,
        ], $this->comprobante, Auth::id());

        $this->mostrarModalGasto = false;
        session()->flash('status', 'Gasto registrado correctamente.');
    }

    public function abrirModalFondo(): void
    {
        Gate::authorize('caja_chica.gestionar');

        $this->resetValidation();
        $this->montoFondo = '';
        $this->mostrarModalFondo = true;
    }

    public function registrarFondo(CajaChicaService $service): void
    {
        Gate::authorize('caja_chica.gestionar');

        $this->validate(['montoFondo' => 'required|numeric|min:0.01']);

        $service->asignarOReponerFondo((float) $this->montoFondo, Auth::id());

        $this->mostrarModalFondo = false;
        session()->flash('status', 'Fondo actualizado.');
    }

    public function exportarPdf(CajaChicaService $service)
    {
        abort_unless(Auth::user()->hasPermissionTo('caja_chica.ver'), 403);

        $inicio = Carbon::parse("{$this->mes}-01")->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $datos = [
            'mesLabel' => ucfirst($inicio->translatedFormat('F Y')),
            'saldoActual' => $service->saldoActual(),
            'movimientos' => $service->movimientosDelPeriodo($inicio, $fin),
        ];

        $nombreArchivo = 'caja-chica-'.$inicio->format('Y-m').'.pdf';

        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.caja-chica', $datos)->output()),
            $nombreArchivo,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function with(CajaChicaService $service): array
    {
        $inicio = Carbon::parse("{$this->mes}-01")->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        return [
            'mesLabel' => ucfirst($inicio->translatedFormat('F Y')),
            'saldoActual' => $service->saldoActual(),
            'tieneFondoAsignado' => $service->tieneFondoAsignado(),
            'movimientos' => $service->movimientosDelPeriodo($inicio, $fin),
            'categorias' => CategoriaEgresoEnum::cases(),
            'puedeGestionar' => Auth::user()->hasPermissionTo('caja_chica.gestionar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Caja chica</h1>
        <p class="mt-1 text-sm text-ink-dim">Fondo fijo para gastos menores, aparte del Flujo de Caja.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Saldo actual del fondo</p>
            <p @class(['mt-1 font-display text-3xl', 'text-ok' => $saldoActual >= 0, 'text-danger' => $saldoActual < 0])>
                S/ {{ number_format($saldoActual, 2) }}
            </p>
        </div>

        @can('caja_chica.gestionar')
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-surface shadow-sm p-4">
                <x-secondary-button type="button" wire:click="abrirModalFondo">
                    {{ $tieneFondoAsignado ? 'Reponer fondo' : 'Asignar fondo' }}
                </x-secondary-button>
                <x-primary-button type="button" wire:click="abrirModalGasto" class="gap-2">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Registrar gasto
                </x-primary-button>
            </div>
        @endcan
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button type="button" wire:click="mesAnterior" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                <x-heroicon-o-chevron-left class="h-4 w-4" />
            </button>
            <p class="font-display text-lg capitalize text-ink">{{ $mesLabel }}</p>
            <button type="button" wire:click="mesSiguiente" class="rounded-md border border-border p-2 text-ink-faint transition hover:border-accent hover:text-accent">
                <x-heroicon-o-chevron-right class="h-4 w-4" />
            </button>
        </div>

        <x-secondary-button type="button" wire:click="exportarPdf" wire:loading.attr="disabled" wire:target="exportarPdf">
            Exportar PDF
        </x-secondary-button>
    </div>

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
                            'bg-ok/10 text-ok' => $movimiento->tipo->sumaAlSaldo(),
                            'bg-danger/10 text-danger' => ! $movimiento->tipo->sumaAlSaldo(),
                        ])>
                            {{ $movimiento->tipo->label() }}
                        </span>
                        <div>
                            <p class="text-ink">{{ $movimiento->categoria?->label() ?? $movimiento->tipo->label() }}{{ $movimiento->descripcion ? " — {$movimiento->descripcion}" : '' }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $movimiento->fecha->format('d/m/Y') }}
                                @if ($movimiento->registradoPor)
                                    · {{ $movimiento->registradoPor->name }}
                                @endif
                                @if ($movimiento->getFirstMedia('comprobante'))
                                    · <a href="{{ $movimiento->getFirstMediaUrl('comprobante') }}" target="_blank" class="font-medium text-accent hover:underline">Comprobante</a>
                                @endif
                            </p>
                        </div>
                    </div>
                    <p @class(['font-display', 'text-ok' => $movimiento->tipo->sumaAlSaldo(), 'text-danger' => ! $movimiento->tipo->sumaAlSaldo()])>
                        {{ $movimiento->tipo->sumaAlSaldo() ? '+' : '−' }} S/ {{ number_format((float) $movimiento->monto, 2) }}
                    </p>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">Sin movimientos en {{ $mesLabel }}.</p>
            @endforelse
        </div>
    </div>

    <div
        x-show="$wire.mostrarModalGasto"
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
            x-show="$wire.mostrarModalGasto"
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">Registrar gasto</h2>

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
                        <x-input-label for="fechaGasto" value="Fecha" />
                        <x-date-input wire:model="fechaGasto" id="fechaGasto" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fechaGasto')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="comprobante" value="Comprobante (opcional)" />
                    <input wire:model="comprobante" id="comprobante" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('comprobante')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModalGasto', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="registrarGasto">Registrar</x-primary-button>
            </div>
        </div>
    </div>

    <div
        x-show="$wire.mostrarModalFondo"
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
            x-show="$wire.mostrarModalFondo"
            class="w-full max-w-sm rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <h2 class="font-display text-lg text-ink">{{ $tieneFondoAsignado ? 'Reponer fondo' : 'Asignar fondo' }}</h2>
            <p class="mt-1 text-sm text-ink-dim">
                {{ $tieneFondoAsignado ? 'Indica cuánto se agrega al fondo.' : 'Indica el monto inicial del fondo de caja chica.' }}
            </p>

            <div class="mt-4">
                <x-input-label for="montoFondo" value="Monto (S/)" />
                <x-text-input wire:model="montoFondo" id="montoFondo" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('montoFondo')" class="mt-1" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="$set('mostrarModalFondo', false)">Cancelar</x-secondary-button>
                <x-primary-button type="button" wire:click="registrarFondo">Guardar</x-primary-button>
            </div>
        </div>
    </div>
</div>
