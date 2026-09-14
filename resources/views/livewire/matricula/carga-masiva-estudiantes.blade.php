<?php

use App\Modules\Matricula\Exports\EstudiantesPlantillaExport;
use App\Modules\Matricula\Imports\HojaConEncabezadosImport;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $archivo = null;

    /** @var array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}|null */
    public ?array $resultado = null;

    public function mount(): void
    {
        Gate::authorize('matricula.crear');
    }

    public function descargarPlantilla()
    {
        Gate::authorize('matricula.crear');

        return Excel::download(new EstudiantesPlantillaExport, 'plantilla-estudiantes.xlsx');
    }

    public function procesar(MatriculaService $service): void
    {
        Gate::authorize('matricula.crear');

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $import = new HojaConEncabezadosImport;
        Excel::import($import, $this->archivo);

        $this->resultado = $service->registrarEstudiantesDesdeFilas($import->filas);
        $this->reset('archivo');
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Carga masiva de estudiantes</h1>
        <p class="mt-1 text-sm text-ink-dim">Registra muchos estudiantes a la vez subiendo un archivo Excel.</p>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-display text-lg text-ink">1. Descarga la plantilla</h2>
                    <p class="mt-1 text-sm text-ink-dim">
                        Un estudiante por fila. Si es menor de edad, completa también las columnas de apoderado
                        (obligatorias solo en ese caso).
                    </p>
                </div>
                <x-secondary-button type="button" wire:click="descargarPlantilla" class="shrink-0">
                    <x-heroicon-o-arrow-down-tray class="me-1.5 h-4 w-4" />
                    Descargar plantilla
                </x-secondary-button>
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="font-display text-lg text-ink">2. Sube el archivo completo</h2>

            <form wire:submit="procesar" class="mt-4 space-y-4">
                <div>
                    <input
                        type="file"
                        wire:model="archivo"
                        accept=".xlsx,.xls,.csv"
                        class="block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink"
                    >
                    <div wire:loading wire:target="archivo" class="mt-1 text-xs text-ink-faint">Subiendo…</div>
                    <x-input-error :messages="$errors->get('archivo')" class="mt-1" />
                </div>

                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="procesar">
                    <span wire:loading.remove wire:target="procesar">Procesar archivo</span>
                    <span wire:loading wire:target="procesar">Procesando…</span>
                </x-primary-button>
            </form>
        </div>

        @if ($resultado)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="font-display text-lg text-ink">Resultado</h2>

                <div class="mt-3 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
                    {{ $resultado['exitosos'] }} estudiante{{ $resultado['exitosos'] === 1 ? '' : 's' }} registrado{{ $resultado['exitosos'] === 1 ? '' : 's' }} correctamente.
                </div>

                @if (count($resultado['errores']) > 0)
                    <div class="mt-4">
                        <p class="text-sm font-semibold text-danger">{{ count($resultado['errores']) }} fila{{ count($resultado['errores']) === 1 ? '' : 's' }} con errores:</p>
                        <div class="mt-2 max-h-80 overflow-y-auto rounded-md border border-border">
                            <table class="min-w-full divide-y divide-border text-sm">
                                <thead class="bg-surface-2">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Fila</th>
                                        <th class="px-3 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($resultado['errores'] as $error)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-ink-dim">{{ $error['fila'] }}</td>
                                            <td class="px-3 py-2 text-danger">{{ $error['mensaje'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
