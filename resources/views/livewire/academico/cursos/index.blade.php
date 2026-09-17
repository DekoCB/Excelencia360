<?php

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Academico\Services\CursoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $codigo = '';

    /** @var list<int> */
    public array $gradoIds = [];

    /** @var list<string> */
    public array $franjasPermitidas = [];

    public string $horas = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $cursoId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $cursoId;

        if ($cursoId) {
            $curso = Curso::query()->with('grados')->findOrFail($cursoId);
            $this->nombre = $curso->nombre;
            $this->codigo = $curso->codigo;
            $this->gradoIds = $curso->grados->pluck('id')->all();
            $this->franjasPermitidas = $curso->franjas_permitidas ?? [];
            $this->horas = (string) $curso->horas;
            $this->activo = $curso->activo;
        } else {
            $this->reset(['nombre', 'codigo', 'gradoIds', 'franjasPermitidas', 'horas']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function updatedNombre(CursoService $service): void
    {
        $this->sugerirCodigo($service);
    }

    public function updatedGradoIds(CursoService $service): void
    {
        $this->sugerirCodigo($service);
    }

    /**
     * Solo sugiere el código para un curso nuevo: si se está editando uno
     * existente, su código ya fue asignado y no debe pisarse sin querer al
     * corregir el nombre o los semestres. Un curso puede quedar en varios
     * semestres a la vez: el código se basa en el de menor orden (el
     * "primer" semestre donde se dicta), sin que eso implique que los
     * demás sean menos válidos.
     */
    private function sugerirCodigo(CursoService $service): void
    {
        if ($this->editandoId || trim($this->nombre) === '' || $this->gradoIds === []) {
            return;
        }

        $grado = Grado::query()->whereIn('id', $this->gradoIds)->orderBy('orden')->first();

        if (! $grado) {
            return;
        }

        $this->codigo = $service->generarCodigo($this->nombre, $grado);
    }

    public function guardar(CursoService $service): void
    {
        Gate::authorize('academico.gestionar');

        // El código se recalcula aquí (no solo en los hooks updated*) para
        // que un envío antes de que el debounce del nombre dispare no deje
        // el campo vacío: al crear, el código nunca lo escribe la persona.
        if (! $this->editandoId && $this->gradoIds !== []) {
            $grado = Grado::query()->whereIn('id', $this->gradoIds)->orderBy('orden')->first();

            if ($grado) {
                $this->codigo = $service->generarCodigo($this->nombre, $grado);
            }
        }

        $this->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:20',
            'gradoIds' => 'required|array|min:1',
            'gradoIds.*' => 'integer|exists:grados,id',
            'franjasPermitidas' => 'array',
            'franjasPermitidas.*' => 'string|in:'.implode(',', array_column(FranjaHorarioEnum::cases(), 'value')),
            'horas' => 'required|integer|min:1|max:500',
        ]);

        if (! $service->codigoDisponible($this->codigo, $this->editandoId)) {
            $this->addError('codigo', 'Ya existe un curso con este código.');

            return;
        }

        $datos = [
            'nombre' => $this->nombre,
            'codigo' => strtoupper($this->codigo),
            'grado_ids' => array_map('intval', $this->gradoIds),
            'franjas_permitidas' => $this->franjasPermitidas !== [] ? $this->franjasPermitidas : null,
            'horas' => (int) $this->horas,
        ];

        if ($this->editandoId) {
            $service->actualizar(Curso::query()->findOrFail($this->editandoId), [...$datos, 'activo' => $this->activo]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Curso guardado correctamente.');
    }

    public function with(CursoService $service): array
    {
        $cursos = $service->listar();
        $ciclo = Ciclo::query()->where('estado', 'activo')->first();

        // El docente se muestra para el período activo: un curso puede
        // tener horarios distintos (o ninguno) según el período, así que
        // no tiene sentido mezclarlos todos en una sola columna.
        $horariosPorCurso = $ciclo
            ? Horario::query()
                ->where('ciclo_id', $ciclo->id)
                ->whereIn('curso_id', $cursos->pluck('id'))
                ->with('docente')
                ->get()
                ->groupBy('curso_id')
            : collect();

        return [
            'cursos' => $cursos,
            'programas' => ProgramaEstudio::query()
                ->where('activo', true)
                ->with(['grados' => fn ($query) => $query->where('activo', true)->orderBy('orden')])
                ->orderBy('nombre')
                ->get(),
            'ciclo' => $ciclo,
            'horariosPorCurso' => $horariosPorCurso,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Cursos</h1>
        <p class="mt-1 text-sm text-ink-dim">Catálogo de cursos por semestre.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo curso
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <p class="mb-4 text-xs text-ink-faint">
        @if ($ciclo)
            Docente del ciclo activo: {{ $ciclo->nombre }}.
        @else
            No hay un ciclo activo: no se puede mostrar el docente.
        @endif
    </p>

    <div class="overflow-x-auto rounded-2xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Código</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Semestres</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Docente</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Horas</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($cursos as $curso)
                    @php
                        $horariosDelCurso = $horariosPorCurso[$curso->id] ?? collect();
                        $docentes = $horariosDelCurso->pluck('docente.name')->filter()->unique()->values();
                    @endphp
                    <tr wire:key="curso-{{ $curso->id }}">
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $curso->codigo }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ $curso->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $curso->grados->pluck('nombre')->implode(', ') }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $docentes->isNotEmpty() ? $docentes->implode(', ') : '—' }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $curso->horas }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $curso->activo, 'bg-ink-faint/10 text-ink-faint' => ! $curso->activo])>
                                {{ $curso->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $curso->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-ink-faint">No hay cursos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cursos->links() }}</div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        wire:click.self="$set('mostrarModal', false)"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="w-full max-w-md rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar curso' : 'Nuevo curso' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model.live.debounce.400ms="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Semestres en los que se dicta" />
                        <p class="mt-1 text-xs text-ink-faint">Un curso puede pertenecer a varios programas de estudio a la vez.</p>
                        <div class="mt-2 max-h-48 space-y-3 overflow-y-auto rounded-md border border-border p-3">
                            @forelse ($programas as $programa)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $programa->nombre }}</p>
                                    <div class="mt-1 space-y-1">
                                        @forelse ($programa->grados as $grado)
                                            <label class="flex items-center gap-2 text-sm text-ink-dim">
                                                <input type="checkbox" wire:model.live="gradoIds" value="{{ $grado->id }}" class="rounded border-border text-accent focus:ring-accent">
                                                {{ $grado->nombre }}
                                            </label>
                                        @empty
                                            <p class="text-xs text-ink-faint">Sin semestres.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-ink-faint">No hay programas de estudio activos. Crea uno primero.</p>
                            @endforelse
                        </div>
                        <x-input-error :messages="$errors->get('gradoIds')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Franjas en las que se dicta (opcional)" />
                        <p class="mt-1 text-xs text-ink-faint">Si no marcas ninguna, se puede crear el horario en cualquiera de las 3.</p>
                        <div class="mt-2 space-y-1">
                            @foreach (\App\Modules\Academico\Enums\FranjaHorarioEnum::cases() as $franja)
                                <label class="flex items-center gap-2 text-sm text-ink-dim">
                                    <input type="checkbox" wire:model="franjasPermitidas" value="{{ $franja->value }}" class="rounded border-border text-accent focus:ring-accent">
                                    {{ $franja->label() }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('franjasPermitidas')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="codigo" value="Código" />
                            @if ($editandoId)
                                <x-text-input wire:model="codigo" id="codigo" class="mt-1 block w-full uppercase" />
                            @else
                                <div id="codigo" class="mt-1 flex h-[42px] items-center rounded-md border border-border bg-surface-2 px-3 font-mono text-sm text-ink-dim">
                                    {{ $codigo !== '' ? $codigo : 'Se genera automáticamente' }}
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('codigo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="horas" value="Horas" />
                            <x-text-input wire:model="horas" id="horas" type="number" min="1" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('horas')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Curso activo
                        </label>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
