<?php

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Matricula\Exports\MatriculaCursoCortoPlantillaExport;
use App\Modules\Matricula\Exports\MatriculaMasivaPlantillaExport;
use App\Modules\Matricula\Imports\HojaConEncabezadosImport;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tipoMatricula = 'instituto';

    public string $cicloId = '';

    public $archivo = null;

    /** @var array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}|null */
    public ?array $resultado = null;

    // Fijo cuando quien matricula es una secretaría acotada a una sola
    // modalidad -- en ese caso el selector de tipo no se muestra, mismo
    // criterio que matricula/wizard.blade.php.
    public ?string $modalidadRestringida = null;

    public function mount(): void
    {
        Gate::authorize('matricula.crear');

        $this->modalidadRestringida = Auth::user()->modalidadMatriculaRestringida();

        if ($this->modalidadRestringida !== null) {
            $this->tipoMatricula = $this->modalidadRestringida;
        }
    }

    public function updatedTipoMatricula(): void
    {
        $this->cicloId = '';
        $this->resultado = null;
    }

    public function descargarPlantilla()
    {
        Gate::authorize('matricula.crear');

        return $this->tipoMatricula === 'curso_corto'
            ? Excel::download(new MatriculaCursoCortoPlantillaExport, 'plantilla-matricula-curso-corto.xlsx')
            : Excel::download(new MatriculaMasivaPlantillaExport, 'plantilla-matricula-instituto.xlsx');
    }

    public function procesar(MatriculaService $service): void
    {
        Gate::authorize('matricula.crear');

        if ($this->tipoMatricula === 'curso_corto') {
            $this->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']]);

            $import = new HojaConEncabezadosImport;
            Excel::import($import, $this->archivo);

            $this->resultado = $service->matricularCursoCortoDesdeFilas($import->filas, Auth::id());
        } else {
            $this->validate([
                'cicloId' => ['required', 'integer', 'exists:ciclos,id'],
                'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
            ]);

            $import = new HojaConEncabezadosImport;
            Excel::import($import, $this->archivo);

            $this->resultado = $service->matricularDesdeFilas((int) $this->cicloId, $import->filas, Auth::id());
        }

        $this->reset('archivo');
    }

    /**
     * El período de matrícula de Instituto es el propio Período Académico
     * vigente: un ciclo anual cuenta mientras esté activo (igual que
     * matricula/wizard.blade.php, que ya autoasigna el ciclo anual vigente
     * sin pedir período aparte); uno de 6 meses sigue dependiendo de su
     * ventana de admisión propia (fecha_inicio_matricula/fecha_fin_matricula).
     * Antes esto exigía estado_matricula='abierto' para cualquier ciclo, algo
     * que un ciclo anual nunca tiene (ver CicloService::prepararFechasDeMatricula()),
     * así que Instituto -- que opera sobre todo con ciclos anuales -- nunca
     * encontraba ninguno.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Ciclo>
     */
    private function ciclosDisponibles(): \Illuminate\Database\Eloquent\Collection
    {
        return Ciclo::query()
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('modalidad', ModalidadCicloEnum::ANUAL)->where('estado', EstadoCicloEnum::ACTIVO))
                    ->orWhere(fn ($q) => $q->where('modalidad', ModalidadCicloEnum::SEIS_MESES)
                        ->where('estado_matricula', 'abierto')
                        ->where('fecha_inicio_matricula', '<=', now())
                        ->where('fecha_fin_matricula', '>=', now()));
            })
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    public function with(): array
    {
        return [
            'ciclosDisponibles' => $this->tipoMatricula === 'instituto' ? $this->ciclosDisponibles() : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Matrícula masiva</h1>
        <p class="mt-1 text-sm text-ink-dim">Matricula a muchos estudiantes ya registrados a la vez, subiendo un archivo Excel.</p>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        @if (! $modalidadRestringida)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <x-input-label for="tipoMatricula" value="Tipo de matrícula" />
                <x-select-input
                    wire:model.live="tipoMatricula"
                    id="tipoMatricula"
                    class="mt-1 block w-full sm:max-w-xs"
                    :options="['instituto' => 'Instituto', 'curso_corto' => 'Curso corto']"
                />
            </div>
        @endif

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-display text-lg text-ink">1. Descarga la plantilla</h2>
                    @if ($tipoMatricula === 'curso_corto')
                        <p class="mt-1 text-sm text-ink-dim">
                            Una fila por estudiante: su DNI (ya debe estar registrado) y el nombre exacto del curso corto.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-ink-dim">
                            Una fila por estudiante: su DNI (ya debe estar registrado), carrera y semestre exactos. La
                            columna «curso» es opcional -- si se llena, matricula solo en ese curso del semestre; si se
                            deja vacía, matricula en todos los cursos del semestre.
                        </p>
                    @endif
                </div>
                <x-secondary-button type="button" wire:click="descargarPlantilla" class="shrink-0">
                    <x-heroicon-o-arrow-down-tray class="me-1.5 h-4 w-4" />
                    Descargar plantilla
                </x-secondary-button>
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
            <h2 class="font-display text-lg text-ink">2. {{ $tipoMatricula === 'curso_corto' ? 'Sube el archivo' : 'Elige el período y sube el archivo' }}</h2>

            @if ($tipoMatricula === 'instituto' && $ciclosDisponibles->isEmpty())
                <p class="mt-3 text-sm text-danger">No hay ningún período académico vigente ni período de matrícula abierto hoy.</p>
            @endif

            <form wire:submit="procesar" class="mt-4 space-y-4">
                @if ($tipoMatricula === 'instituto')
                    <div>
                        <x-input-label for="cicloId" value="Período de matrícula" />
                        <x-select-input
                            wire:model="cicloId"
                            id="cicloId"
                            class="mt-1 block w-full sm:max-w-xs"
                            :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                        />
                        <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-input-label for="archivo" value="Archivo" />
                    <input
                        type="file"
                        wire:model="archivo"
                        id="archivo"
                        accept=".xlsx,.xls,.csv"
                        class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink"
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
                    {{ $resultado['exitosos'] }} estudiante{{ $resultado['exitosos'] === 1 ? '' : 's' }} matriculado{{ $resultado['exitosos'] === 1 ? '' : 's' }} correctamente.
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
