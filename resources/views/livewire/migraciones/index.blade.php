<?php

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CicloService;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Migraciones\Services\MigracionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $tab = 'individual';

    // Individual
    public string $terminoBusqueda = '';

    public ?int $estudianteId = null;

    public string $estudianteNombre = '';

    public string $cicloDestinoId = '';

    public string $gradoDestinoId = '';

    // Masivo
    public string $modalidadOrigen = '';

    public string $cicloOrigenId = '';

    public string $seccionOrigen = '';

    public string $gradoOrigenId = '';

    public string $masivoCicloDestinoId = '';

    public string $masivoGradoDestinoId = '';

    /** @var array{exitosos: int, errores: list<array{estudiante: string, mensaje: string}>}|null */
    public ?array $resultado = null;

    public function mount(): void
    {
        Gate::authorize('migraciones.ver');
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre, MigracionService $service, CicloService $ciclos): void
    {
        $this->estudianteId = $estudianteId;
        $this->estudianteNombre = $nombre;
        $this->terminoBusqueda = '';

        $origen = $this->matriculaVigenteDe($estudianteId);

        if ($origen) {
            $cicloSugerido = $service->cicloDestinoSugerido($origen->ciclo, $ciclos);
            $gradoSugerido = $service->gradoSiguiente($origen->grado);
            $this->cicloDestinoId = $cicloSugerido ? (string) $cicloSugerido->id : '';
            $this->gradoDestinoId = $gradoSugerido ? (string) $gradoSugerido->id : '';
        }
    }

    public function cambiarEstudiante(): void
    {
        $this->reset(['estudianteId', 'estudianteNombre', 'cicloDestinoId', 'gradoDestinoId']);
    }

    public function migrarIndividual(MigracionService $service): void
    {
        Gate::authorize('migraciones.gestionar');

        $this->validate([
            'estudianteId' => 'required|integer|exists:estudiantes,id',
            'cicloDestinoId' => 'required|integer|exists:ciclos,id',
            'gradoDestinoId' => 'required|integer|exists:grados,id',
        ]);

        $origen = $this->matriculaVigenteDe($this->estudianteId);

        if ($origen === null) {
            $this->addError('estudianteId', 'Este estudiante no tiene una matrícula vigente.');

            return;
        }

        $service->migrar($origen, (int) $this->cicloDestinoId, (int) $this->gradoDestinoId, Auth::id());

        $this->reset(['estudianteId', 'estudianteNombre', 'cicloDestinoId', 'gradoDestinoId']);
        session()->flash('status', 'Estudiante migrado correctamente.');
    }

    public function updatedModalidadOrigen(): void
    {
        $this->cicloOrigenId = '';
        $this->seccionOrigen = '';
        $this->gradoOrigenId = '';
        $this->masivoCicloDestinoId = '';
        $this->masivoGradoDestinoId = '';
    }

    public function updatedSeccionOrigen(): void
    {
        $this->gradoOrigenId = '';
        $this->masivoGradoDestinoId = '';
    }

    public function updatedGradoOrigenId(MigracionService $service, CicloService $ciclos): void
    {
        if ($this->gradoOrigenId === '') {
            $this->masivoGradoDestinoId = '';

            return;
        }

        $grado = Grado::query()->find($this->gradoOrigenId);
        $gradoSugerido = $grado ? $service->gradoSiguiente($grado) : null;
        $this->masivoGradoDestinoId = $gradoSugerido ? (string) $gradoSugerido->id : '';

        $cicloOrigen = $this->cicloOrigenParaSugerencia($service);

        if ($cicloOrigen) {
            $cicloSugerido = $service->cicloDestinoSugerido($cicloOrigen, $ciclos);
            $this->masivoCicloDestinoId = $cicloSugerido ? (string) $cicloSugerido->id : '';
        }
    }

    public function migrarMasivo(MigracionService $service): void
    {
        Gate::authorize('migraciones.gestionar');

        $this->validate([
            'modalidadOrigen' => 'required|string|in:seis_meses,anual',
            'gradoOrigenId' => 'required|integer|exists:grados,id',
            'masivoCicloDestinoId' => 'required|integer|exists:ciclos,id',
            'masivoGradoDestinoId' => 'required|integer|exists:grados,id',
        ]);

        $origenes = $this->cohorteMasivaActual($service);

        $this->resultado = $service->migrarMasivo($origenes, (int) $this->masivoCicloDestinoId, (int) $this->masivoGradoDestinoId, Auth::id());
    }

    private function matriculaVigenteDe(int $estudianteId): ?Matricula
    {
        return Matricula::query()
            ->where('estudiante_id', $estudianteId)
            ->where('estado', EstadoMatriculaEnum::APROBADA)
            ->latest('fecha_matricula')
            ->with(['ciclo', 'grado'])
            ->first();
    }

    /**
     * El "Grupo" con el que se sugiere el destino: para 6 meses es el que
     * el usuario eligió; SIAGIE anual no tiene selector de Grupo (ver
     * MigracionService::cicloAnualVigente()), así que se usa ese
     * automáticamente.
     */
    private function cicloOrigenParaSugerencia(MigracionService $service): ?Ciclo
    {
        if ($this->modalidadOrigen === ModalidadCicloEnum::ANUAL->value) {
            return $service->cicloAnualVigente();
        }

        return $this->cicloOrigenId !== '' ? Ciclo::query()->find($this->cicloOrigenId) : null;
    }

    /**
     * @return Collection<int, Matricula>
     */
    private function cohorteMasivaActual(MigracionService $service): Collection
    {
        if ($this->modalidadOrigen === '' || $this->gradoOrigenId === '') {
            return new Collection;
        }

        $modalidad = ModalidadCicloEnum::from($this->modalidadOrigen);

        $cicloId = $this->modalidadOrigen === ModalidadCicloEnum::ANUAL->value
            ? $service->cicloAnualVigente()?->id
            : ($this->cicloOrigenId !== '' ? (int) $this->cicloOrigenId : null);

        return $service->matriculasVigentes(
            $modalidad,
            $cicloId,
            $this->seccionOrigen !== '' ? $this->seccionOrigen : null,
            (int) $this->gradoOrigenId,
        );
    }

    public function with(MigracionService $service): array
    {
        $resultadosBusqueda = collect();

        if ($this->terminoBusqueda !== '') {
            $termino = $this->terminoBusqueda;
            $resultadosBusqueda = Estudiante::query()
                ->where(function ($query) use ($termino) {
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $gradosOrigenDisponibles = Grado::query()
            ->where('activo', true)
            ->when($this->seccionOrigen !== '', fn ($q) => $q->deSeccion($this->seccionOrigen))
            ->orderBy('orden')
            ->get();

        return [
            'resultadosBusqueda' => $resultadosBusqueda,
            'matriculaOrigenIndividual' => $this->estudianteId ? $this->matriculaVigenteDe($this->estudianteId) : null,
            'ciclos' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'ciclosSeisMeses' => Ciclo::query()->where('modalidad', ModalidadCicloEnum::SEIS_MESES)->orderByDesc('fecha_inicio')->get(),
            'cicloAnualVigente' => $service->cicloAnualVigente(),
            'grados' => Grado::query()->where('activo', true)->orderBy('orden')->get(),
            'gradosOrigenDisponibles' => $gradosOrigenDisponibles,
            'cohorteMasiva' => $this->cohorteMasivaActual($service),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Migraciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Pasar de grado a un estudiante, o a varios a la vez filtrados por Modalidad/Grupo/Sección/Grado.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        <button type="button" wire:click="$set('tab', 'individual')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'individual', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'individual'])>
            Individual
        </button>
        <button type="button" wire:click="$set('tab', 'masivo')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'masivo', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'masivo'])>
            Masivo
        </button>
    </div>

    {{-- Individual --}}
    @if ($tab === 'individual')
        <div class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div>
                <x-input-label value="Estudiante" />
                @if ($estudianteId)
                    <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                        {{ $estudianteNombre }}
                        <button type="button" wire:click="cambiarEstudiante" class="text-xs underline">Cambiar</button>
                    </div>
                @else
                    <x-text-input wire:model.live.debounce.300ms="terminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre, apellido o DNI…" />
                    @if ($resultadosBusqueda->isNotEmpty())
                        <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                            @foreach ($resultadosBusqueda as $estudiante)
                                <button
                                    type="button"
                                    wire:click="seleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                >
                                    {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                <x-input-error :messages="$errors->get('estudianteId')" class="mt-1" />
            </div>

            @if ($matriculaOrigenIndividual)
                <p class="text-sm text-ink-dim">
                    Actualmente en <span class="font-medium text-ink">{{ $matriculaOrigenIndividual->grado->nombre }}</span>
                    · {{ $matriculaOrigenIndividual->ciclo->nombre }} ({{ $matriculaOrigenIndividual->ciclo->modalidad->label() }})
                </p>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="cicloDestinoId" value="Ciclo destino" />
                        <x-select-input
                            wire:model="cicloDestinoId"
                            id="cicloDestinoId"
                            class="mt-1 block w-full"
                            :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                        />
                        <x-input-error :messages="$errors->get('cicloDestinoId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gradoDestinoId" value="Grado destino" />
                        <x-select-input
                            wire:model="gradoDestinoId"
                            id="gradoDestinoId"
                            class="mt-1 block w-full"
                            :options="collect($grados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])"
                        />
                        <x-input-error :messages="$errors->get('gradoDestinoId')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button
                        type="button"
                        x-data
                        x-on:click="$store.confirm.preguntar('¿Migrar a {{ addslashes($estudianteNombre) }} al grado destino elegido?', () => $wire.migrarIndividual(), { etiquetaConfirmar: 'Migrar' })"
                    >
                        Migrar
                    </x-primary-button>
                </div>
            @endif
        </div>
    @endif

    {{-- Masivo --}}
    @if ($tab === 'masivo')
        <div class="space-y-4">
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <h2 class="font-display text-sm text-ink">Origen</h2>
                <p class="mt-1 text-xs text-ink-faint">Primero elige la modalidad — el de 6 meses se filtra por Grupo, SIAGIE anual no tiene Grupos (no rota).</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-input-label for="modalidadOrigen" value="Modalidad" />
                        <x-select-input
                            wire:model.live="modalidadOrigen"
                            id="modalidadOrigen"
                            class="mt-1 block w-full"
                            :options="collect(['' => 'Selecciona…'])->merge(collect(\App\Modules\Academico\Enums\ModalidadCicloEnum::cases())->mapWithKeys(fn ($modalidad) => [$modalidad->value => $modalidad->label()]))"
                        />
                        <x-input-error :messages="$errors->get('modalidadOrigen')" class="mt-1" />
                    </div>

                    @if ($modalidadOrigen === 'seis_meses')
                        <div>
                            <x-input-label for="cicloOrigenId" value="Grupo" />
                            <x-select-input
                                wire:model.live="cicloOrigenId"
                                id="cicloOrigenId"
                                class="mt-1 block w-full"
                                :options="collect($ciclosSeisMeses)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])->prepend('Todos los grupos', '')"
                            />
                        </div>
                    @elseif ($modalidadOrigen === 'anual')
                        <div>
                            <x-input-label value="Año" />
                            @if ($cicloAnualVigente)
                                <p class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-ink">{{ $cicloAnualVigente->anio }}</p>
                            @else
                                <p class="mt-1 text-xs text-danger">No hay ningún ciclo SIAGIE anual registrado todavía. Créalo primero en Ciclos.</p>
                            @endif
                        </div>
                    @endif

                    @if ($modalidadOrigen !== '')
                        <div>
                            <x-input-label for="seccionOrigen" value="Sección" />
                            <x-select-input
                                wire:model.live="seccionOrigen"
                                id="seccionOrigen"
                                class="mt-1 block w-full"
                                :options="['' => 'Todas', 'A' => 'Aula A', 'B' => 'Aula B']"
                            />
                        </div>
                        <div>
                            <x-input-label for="gradoOrigenId" value="Grado" />
                            <x-select-input
                                wire:model.live="gradoOrigenId"
                                id="gradoOrigenId"
                                class="mt-1 block w-full"
                                :options="collect($gradosOrigenDisponibles)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Selecciona…', '')"
                            />
                            <x-input-error :messages="$errors->get('gradoOrigenId')" class="mt-1" />
                        </div>
                    @endif
                </div>
            </div>

            @if ($modalidadOrigen !== '' && $gradoOrigenId !== '')
                <div class="rounded-2xl border border-border bg-surface shadow-sm">
                    <div class="border-b border-border px-4 py-3">
                        <h3 class="font-display text-sm text-ink">{{ $cohorteMasiva->count() }} estudiante{{ $cohorteMasiva->count() === 1 ? '' : 's' }} coincide{{ $cohorteMasiva->count() === 1 ? '' : 'n' }}</h3>
                    </div>
                    <div class="max-h-64 divide-y divide-border overflow-y-auto">
                        @forelse ($cohorteMasiva as $matricula)
                            <p class="px-4 py-2 text-sm text-ink">
                                {{ $matricula->estudiante->nombreCompleto() }}
                                <span class="text-ink-faint">· {{ $matricula->estudiante->dni }}</span>
                            </p>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-ink-faint">Nadie coincide con estos filtros.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <h2 class="font-display text-sm text-ink">Destino</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="masivoCicloDestinoId" value="Ciclo destino" />
                            <x-select-input
                                wire:model="masivoCicloDestinoId"
                                id="masivoCicloDestinoId"
                                class="mt-1 block w-full"
                                :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                            />
                            <x-input-error :messages="$errors->get('masivoCicloDestinoId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="masivoGradoDestinoId" value="Grado destino" />
                            <x-select-input
                                wire:model="masivoGradoDestinoId"
                                id="masivoGradoDestinoId"
                                class="mt-1 block w-full"
                                :options="collect($grados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])"
                            />
                            <x-input-error :messages="$errors->get('masivoGradoDestinoId')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-primary-button
                            type="button"
                            x-data
                            x-on:click="$store.confirm.preguntar('¿Migrar a los {{ $cohorteMasiva->count() }} estudiantes seleccionados al grado destino elegido?', () => $wire.migrarMasivo(), { etiquetaConfirmar: 'Migrar' })"
                        >
                            Migrar {{ $cohorteMasiva->count() }} estudiante{{ $cohorteMasiva->count() === 1 ? '' : 's' }}
                        </x-primary-button>
                    </div>
                </div>
            @endif

            @if ($resultado)
                <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <h2 class="font-display text-lg text-ink">Resultado</h2>

                    <x-alert class="mt-3">
                        {{ $resultado['exitosos'] }} estudiante{{ $resultado['exitosos'] === 1 ? '' : 's' }} migrado{{ $resultado['exitosos'] === 1 ? '' : 's' }} correctamente.
                    </x-alert>

                    @if (count($resultado['errores']) > 0)
                        <div class="mt-4">
                            <p class="text-sm font-semibold text-danger">{{ count($resultado['errores']) }} con errores:</p>
                            <div class="mt-2 max-h-80 overflow-y-auto rounded-md border border-border">
                                <table class="min-w-full divide-y divide-border text-sm">
                                    <thead class="bg-surface-2">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estudiante</th>
                                            <th class="px-3 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Error</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach ($resultado['errores'] as $error)
                                            <tr>
                                                <td class="px-3 py-2 text-ink-dim">{{ $error['estudiante'] }}</td>
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
    @endif
</div>
