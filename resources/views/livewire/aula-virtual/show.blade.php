<?php

use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\PlantillaCursoVirtual;
use App\Modules\AulaVirtual\Models\Publicacion;
use App\Modules\AulaVirtual\Services\ClaseGrabadaService;
use App\Modules\AulaVirtual\Services\ComentarioService;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\PlantillaCursoVirtualService;
use App\Modules\AulaVirtual\Services\PublicacionService;
use App\Modules\AulaVirtual\Services\TareaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public CursoVirtual $curso;

    public string $tab = 'materiales';

    // Nuevo material
    public bool $mostrarFormMaterial = false;

    public string $materialTipo = '';

    public string $materialTitulo = '';

    public string $materialUrl = '';

    public string $materialSemana = '';

    public $materialArchivo = null;

    /** @var array<int, int> */
    public array $materialCursosSeleccionados = [];

    // Nueva clase grabada
    public bool $mostrarFormGrabacion = false;

    public string $grabacionTipo = '';

    public string $grabacionTitulo = '';

    public string $grabacionUrl = '';

    public string $grabacionSemana = '';

    public $grabacionArchivo = null;

    /** @var array<int, int> */
    public array $grabacionCursosSeleccionados = [];

    // Nueva tarea
    public bool $mostrarFormTarea = false;

    public string $tareaTitulo = '';

    public string $tareaDescripcion = '';

    public string $tareaFechaLimite = '';

    public string $tareaPuntajeMax = '20';

    public string $tareaSemana = '';

    /** @var array<int, int> */
    public array $tareaCursosSeleccionados = [];

    // Nueva publicación
    public bool $mostrarFormPublicacion = false;

    public string $publicacionTipo = '';

    public string $publicacionContenido = '';

    /** @var array<int, string> */
    public array $nuevoComentario = [];

    // Nuevo foro
    public bool $mostrarFormForo = false;

    public string $foroTitulo = '';

    public string $foroDescripcion = '';

    public string $foroSemana = '';

    /** @var array<int, int> */
    public array $foroCursosSeleccionados = [];

    /** @var array<int, string> */
    public array $nuevaRespuestaForo = [];

    // Imagen de portada del curso (coordinador/dirección)
    public $nuevaPortada = null;

    // Plantillas de aula virtual
    public string $nombrePlantilla = '';

    public function mount(CursoVirtual $curso): void
    {
        Gate::authorize('view', $curso);

        $this->curso = $curso;
        $this->materialCursosSeleccionados = [$curso->id];
        $this->grabacionCursosSeleccionados = [$curso->id];
        $this->tareaCursosSeleccionados = [$curso->id];
        $this->foroCursosSeleccionados = [$curso->id];
    }

    /**
     * Revalida cada curso elegido en un checklist "subir también a" (no solo
     * el de la página actual): el cliente puede mandar cualquier ID, así que
     * hay que confirmar permisos de gestión sobre cada uno antes de usarlo.
     *
     * @param  array<int, int>  $idsSeleccionados
     * @return Collection<int, CursoVirtual>
     */
    private function cursosSeleccionadosValidos(array $idsSeleccionados): Collection
    {
        return CursoVirtual::query()
            ->whereIn('id', $idsSeleccionados)
            ->get()
            ->filter(fn (CursoVirtual $curso) => Gate::allows('manage', $curso));
    }

    public function crearMaterial(MaterialService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'materialTipo' => 'required|string|in:'.implode(',', array_column(TipoMaterialEnum::cases(), 'value')),
            'materialTitulo' => 'required|string|max:150',
            'materialUrl' => 'nullable|url|max:500',
            'materialArchivo' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png|max:10240',
            'materialSemana' => 'nullable|integer|min:1',
            'materialCursosSeleccionados' => 'required|array|min:1',
            'materialCursosSeleccionados.*' => 'integer|exists:aula_virtual_cursos,id',
        ]);

        $cursosSeleccionados = $this->cursosSeleccionadosValidos($this->materialCursosSeleccionados);

        abort_if($cursosSeleccionados->isEmpty(), 403);

        $service->crearParaVarios(
            $cursosSeleccionados,
            TipoMaterialEnum::from($this->materialTipo),
            $this->materialTitulo,
            $this->materialUrl ?: null,
            $this->materialArchivo,
            $this->materialSemana !== '' ? (int) $this->materialSemana : null,
        );

        $this->reset(['materialTipo', 'materialTitulo', 'materialUrl', 'materialArchivo', 'materialSemana', 'mostrarFormMaterial']);
        $this->materialCursosSeleccionados = [$this->curso->id];
    }

    public function eliminarMaterial(int $materialId, MaterialService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $service->eliminar($this->curso->materiales()->findOrFail($materialId));
    }

    public function crearGrabacion(ClaseGrabadaService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'grabacionTipo' => 'required|string|in:'.implode(',', array_column(TipoClaseGrabadaEnum::cases(), 'value')),
            'grabacionTitulo' => 'required|string|max:150',
            'grabacionUrl' => 'nullable|url|max:500',
            'grabacionArchivo' => 'nullable|file|mimes:mp4,mov,avi,wmv,mkv,webm|max:40000',
            'grabacionSemana' => 'nullable|integer|min:1',
            'grabacionCursosSeleccionados' => 'required|array|min:1',
            'grabacionCursosSeleccionados.*' => 'integer|exists:aula_virtual_cursos,id',
        ]);

        $cursosSeleccionados = $this->cursosSeleccionadosValidos($this->grabacionCursosSeleccionados);

        abort_if($cursosSeleccionados->isEmpty(), 403);

        $service->crearParaVarios(
            $cursosSeleccionados,
            TipoClaseGrabadaEnum::from($this->grabacionTipo),
            $this->grabacionTitulo,
            $this->grabacionUrl ?: null,
            $this->grabacionArchivo,
            $this->grabacionSemana !== '' ? (int) $this->grabacionSemana : null,
        );

        $this->reset(['grabacionTipo', 'grabacionTitulo', 'grabacionUrl', 'grabacionArchivo', 'grabacionSemana', 'mostrarFormGrabacion']);
        $this->grabacionCursosSeleccionados = [$this->curso->id];
    }

    public function eliminarGrabacion(int $claseGrabadaId, ClaseGrabadaService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $service->eliminar($this->curso->clasesGrabadas()->findOrFail($claseGrabadaId));
    }

    public function crearTarea(TareaService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'tareaTitulo' => 'required|string|max:150',
            'tareaDescripcion' => 'nullable|string',
            'tareaFechaLimite' => 'required|date',
            'tareaPuntajeMax' => 'required|integer|min:1|max:20',
            'tareaSemana' => 'nullable|integer|min:1',
            'tareaCursosSeleccionados' => 'required|array|min:1',
            'tareaCursosSeleccionados.*' => 'integer|exists:aula_virtual_cursos,id',
        ]);

        $cursosSeleccionados = $this->cursosSeleccionadosValidos($this->tareaCursosSeleccionados);

        abort_if($cursosSeleccionados->isEmpty(), 403);

        $service->crearParaVarios($cursosSeleccionados, [
            'titulo' => $this->tareaTitulo,
            'descripcion' => $this->tareaDescripcion ?: null,
            'fecha_limite' => $this->tareaFechaLimite,
            'puntaje_max' => (int) $this->tareaPuntajeMax,
            'semana' => $this->tareaSemana !== '' ? (int) $this->tareaSemana : null,
        ]);

        $this->reset(['tareaTitulo', 'tareaDescripcion', 'tareaFechaLimite', 'tareaPuntajeMax', 'tareaSemana', 'mostrarFormTarea']);
        $this->tareaCursosSeleccionados = [$this->curso->id];
    }

    public function crearPublicacion(PublicacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'publicacionTipo' => 'required|string|in:'.implode(',', array_column(TipoPublicacionEnum::cases(), 'value')),
            'publicacionContenido' => 'required|string',
        ]);

        $service->crear($this->curso, auth()->id(), TipoPublicacionEnum::from($this->publicacionTipo), $this->publicacionContenido);

        $this->reset(['publicacionTipo', 'publicacionContenido', 'mostrarFormPublicacion']);
    }

    public function comentar(int $publicacionId, ComentarioService $service): void
    {
        Gate::authorize('view', $this->curso);

        $contenido = trim($this->nuevoComentario[$publicacionId] ?? '');

        if ($contenido === '') {
            return;
        }

        $publicacion = Publicacion::query()->where('curso_virtual_id', $this->curso->id)->findOrFail($publicacionId);

        $service->comentar($publicacion, auth()->id(), $contenido);

        unset($this->nuevoComentario[$publicacionId]);
    }

    public function crearForo(ForoService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'foroTitulo' => 'required|string|max:150',
            'foroDescripcion' => 'nullable|string',
            'foroSemana' => 'nullable|integer|min:1',
            'foroCursosSeleccionados' => 'required|array|min:1',
            'foroCursosSeleccionados.*' => 'integer|exists:aula_virtual_cursos,id',
        ]);

        $cursosSeleccionados = $this->cursosSeleccionadosValidos($this->foroCursosSeleccionados);

        abort_if($cursosSeleccionados->isEmpty(), 403);

        $service->crearParaVarios(
            $cursosSeleccionados,
            auth()->id(),
            $this->foroTitulo,
            $this->foroDescripcion ?: null,
            $this->foroSemana !== '' ? (int) $this->foroSemana : null,
        );

        $this->reset(['foroTitulo', 'foroDescripcion', 'foroSemana', 'mostrarFormForo']);
        $this->foroCursosSeleccionados = [$this->curso->id];
    }

    public function responderForo(int $foroId, ForoService $service): void
    {
        Gate::authorize('view', $this->curso);

        $contenido = trim($this->nuevaRespuestaForo[$foroId] ?? '');

        if ($contenido === '') {
            return;
        }

        $foro = Foro::query()->where('curso_virtual_id', $this->curso->id)->findOrFail($foroId);

        $service->responder($foro, auth()->id(), $contenido);

        unset($this->nuevaRespuestaForo[$foroId]);
    }

    public function updatedNuevaPortada(): void
    {
        $user = Auth::user();
        abort_unless($user->hasRole('coordinador') || $user->hasRole('direccion'), 403);

        $this->validate(['nuevaPortada' => 'image|max:4096']);

        $cursoAcademico = $this->curso->horario->curso;
        $cursoAcademico->addMedia($this->nuevaPortada->getRealPath())
            ->usingFileName('portada-'.$cursoAcademico->id.'.'.$this->nuevaPortada->getClientOriginalExtension())
            ->toMediaCollection('portada');

        $this->nuevaPortada = null;
        session()->flash('status', 'Imagen de portada actualizada.');
    }

    public function guardarPlantilla(PlantillaCursoVirtualService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate(['nombrePlantilla' => 'required|string|max:150']);

        $service->guardarDesdeCursoVirtual($this->curso, $this->nombrePlantilla, Auth::user());

        $this->reset(['nombrePlantilla']);
        $this->dispatch('close-modal', 'guardar-plantilla');
        session()->flash('status', 'Plantilla guardada correctamente.');
    }

    public function aplicarPlantilla(int $plantillaId, PlantillaCursoVirtualService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $plantilla = PlantillaCursoVirtual::query()
            ->where('curso_id', $this->curso->horario->curso_id)
            ->findOrFail($plantillaId);

        $aplicados = $service->aplicar($plantilla, $this->curso, Auth::user());

        $this->dispatch('close-modal', 'aplicar-plantilla');
        session()->flash('status', "Plantilla aplicada: {$aplicados} elementos agregados.");
    }

    public function eliminarPlantilla(int $plantillaId, PlantillaCursoVirtualService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $plantilla = PlantillaCursoVirtual::query()
            ->where('curso_id', $this->curso->horario->curso_id)
            ->findOrFail($plantillaId);

        $service->eliminar($plantilla);

        session()->flash('status', 'Plantilla eliminada.');
    }

    /**
     * Agrupa por número de semana (clave 0 = "Bienvenida", antes de la
     * Semana 1, para el contenido sin clasificar) y ordena las semanas de
     * forma ascendente.
     *
     * @param  Collection<int, mixed>  $items
     * @return SupportCollection<int, Collection<int, mixed>>
     */
    private function agruparPorSemana($items): SupportCollection
    {
        return $items->groupBy(fn ($item) => $item->semana ?? 0)->sortKeys();
    }

    public function with(CursoVirtualService $cursos, PlantillaCursoVirtualService $plantillas): array
    {
        $user = Auth::user();

        return [
            'puedeGestionar' => Gate::allows('manage', $this->curso),
            'puedeGestionarPortada' => $user->hasRole('coordinador') || $user->hasRole('direccion'),
            'materialesPorSemana' => $this->agruparPorSemana($this->curso->materiales),
            'clasesGrabadasPorSemana' => $this->agruparPorSemana($this->curso->clasesGrabadas),
            'tareasPorSemana' => $this->agruparPorSemana($this->curso->tareas()->latest('fecha_limite')->get()),
            'publicaciones' => $this->curso->publicaciones()->with(['autor', 'comentarios.autor'])->latest()->get(),
            'forosPorSemana' => $this->agruparPorSemana($this->curso->foros()->with(['autor', 'respuestas.autor'])->latest()->get()),
            'tiposMaterial' => TipoMaterialEnum::cases(),
            'tiposClaseGrabada' => TipoClaseGrabadaEnum::cases(),
            'tiposPublicacion' => TipoPublicacionEnum::cases(),
            'cursosRelacionados' => $cursos->cursosVirtualesRelacionados($this->curso),
            'plantillasDisponibles' => $plantillas->listarPorCurso($this->curso->horario->curso),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('aula-virtual.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Aula Virtual</a>
        <div class="mt-1 flex items-center gap-2">
            <h1 class="font-display text-2xl text-ink">{{ $curso->horario->curso->nombre }}</h1>
        </div>
        <p class="mt-1 text-sm text-ink-dim">{{ $curso->horario->grado->nombre }} · {{ $curso->horario->ciclo->nombre }} · {{ $curso->horario->docente->name }}</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    {{-- Portada del curso: antes que nada, incluida la "Bienvenida" de cada pestaña. --}}
    @if ($curso->horario->curso->getFirstMediaUrl('portada') || $puedeGestionarPortada)
        <div class="mb-6">
            @if ($curso->horario->curso->getFirstMediaUrl('portada'))
                <img src="{{ $curso->horario->curso->getFirstMediaUrl('portada') }}" alt="" class="h-52 w-full rounded-lg object-cover">
            @else
                <div class="flex h-36 items-center justify-center rounded-lg border border-dashed border-border bg-surface-2 text-sm text-ink-faint">
                    Sin imagen de portada
                </div>
            @endif

            @if ($puedeGestionarPortada)
                <div class="mt-2 flex items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 text-sm font-medium text-ink transition hover:bg-surface-2">
                        <x-heroicon-o-photo class="h-4 w-4" />
                        {{ $curso->horario->curso->getFirstMediaUrl('portada') ? 'Cambiar imagen' : 'Subir imagen' }}
                        <input type="file" wire:model="nuevaPortada" accept="image/*" class="hidden">
                    </label>
                    <span wire:loading wire:target="nuevaPortada" class="text-xs text-ink-faint">Subiendo…</span>
                    <x-input-error :messages="$errors->get('nuevaPortada')" class="text-xs" />
                </div>
            @endif
        </div>
    @endif

    @can('manage', $curso)
        <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
            @if ($plantillasDisponibles->isNotEmpty())
                <x-secondary-button type="button" x-data x-on:click="$dispatch('open-modal', 'aplicar-plantilla')">
                    Aplicar plantilla
                </x-secondary-button>
            @endif
            <x-secondary-button type="button" x-data x-on:click="$dispatch('open-modal', 'guardar-plantilla')">
                Guardar como plantilla
            </x-secondary-button>
        </div>
    @endcan

    <div class="mb-6 flex gap-1 border-b border-border">
        @foreach (['materiales' => 'Materiales', 'clases-grabadas' => 'Clases grabadas', 'tareas' => 'Tareas', 'publicaciones' => 'Publicaciones', 'foros' => 'Foros'] as $valor => $etiqueta)
            <button
                wire:click="$set('tab', '{{ $valor }}')"
                @class([
                    'border-b-2 px-4 py-2 font-display text-sm font-medium transition',
                    'border-accent text-accent' => $tab === $valor,
                    'border-transparent text-ink-faint hover:text-ink' => $tab !== $valor,
                ])
            >
                {{ $etiqueta }}
            </button>
        @endforeach
    </div>

    {{-- Materiales --}}
    @if ($tab === 'materiales')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormMaterial', true)">+ Nuevo material</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormMaterial)
                <form wire:submit="crearMaterial" class="rounded-2xl border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="materialTipo" value="Tipo" />
                            <x-select-input
                                wire:model.live="materialTipo"
                                id="materialTipo"
                                class="mt-1 block w-full"
                                :options="collect($tiposMaterial)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                            />
                            <x-input-error :messages="$errors->get('materialTipo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="materialTitulo" value="Título" />
                            <x-text-input wire:model="materialTitulo" id="materialTitulo" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('materialTitulo')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="materialSemana" value="Semana (opcional)" />
                        <x-text-input wire:model="materialSemana" id="materialSemana" type="number" min="1" class="mt-1 block w-full" placeholder="Ej. 1" />
                        <p class="mt-1 text-xs text-ink-faint">Déjalo vacío para que aparezca en «Bienvenida», antes de la Semana 1.</p>
                        <x-input-error :messages="$errors->get('materialSemana')" class="mt-1" />
                    </div>
                    @if (in_array($materialTipo, ['pdf', 'archivo']))
                        <div>
                            <x-input-label for="materialArchivo" value="Archivo" />
                            <input wire:model="materialArchivo" id="materialArchivo" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                            <x-input-error :messages="$errors->get('materialArchivo')" class="mt-1" />
                        </div>
                    @elseif ($materialTipo !== '')
                        <div>
                            <x-input-label for="materialUrl" value="URL" />
                            <x-text-input wire:model="materialUrl" id="materialUrl" class="mt-1 block w-full" placeholder="https://…" />
                            <x-input-error :messages="$errors->get('materialUrl')" class="mt-1" />
                        </div>
                    @endif

                    <x-aula-virtual.checklist-secciones :secciones="$cursosRelacionados" campo="materialCursosSeleccionados" />

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormMaterial', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($materialesPorSemana as $numeroSemana => $materialesDeSemana)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                            @foreach ($materialesDeSemana as $material)
                                <div class="flex items-center justify-between px-4 py-3 text-sm">
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink-faint">{{ $material->tipo->label() }}</span>
                                        <span class="text-ink">{{ $material->titulo }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @if ($material->tipo->requiereArchivo() && $material->getFirstMedia('archivo'))
                                            <a href="{{ $material->getFirstMediaUrl('archivo') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Descargar</a>
                                        @elseif ($material->url)
                                            <a href="{{ $material->url }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Abrir enlace</a>
                                        @endif
                                        @can('manage', $curso)
                                            <button x-on:click="$store.confirm.preguntar('¿Eliminar este material?', () => $wire.eliminarMaterial({{ $material->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })" class="text-xs font-medium text-danger hover:underline">Eliminar</button>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay materiales.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Clases grabadas --}}
    @if ($tab === 'clases-grabadas')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormGrabacion', true)">+ Nueva clase grabada</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormGrabacion)
                <form wire:submit="crearGrabacion" class="rounded-2xl border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="grabacionTipo" value="Tipo" />
                            <x-select-input
                                wire:model.live="grabacionTipo"
                                id="grabacionTipo"
                                class="mt-1 block w-full"
                                :options="collect($tiposClaseGrabada)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                            />
                            <x-input-error :messages="$errors->get('grabacionTipo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="grabacionTitulo" value="Título" />
                            <x-text-input wire:model="grabacionTitulo" id="grabacionTitulo" class="mt-1 block w-full" placeholder="Ej. Clase del 15 de julio" />
                            <x-input-error :messages="$errors->get('grabacionTitulo')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="grabacionSemana" value="Semana (opcional)" />
                        <x-text-input wire:model="grabacionSemana" id="grabacionSemana" type="number" min="1" class="mt-1 block w-full" placeholder="Ej. 1" />
                        <p class="mt-1 text-xs text-ink-faint">Déjalo vacío para que aparezca en «Bienvenida», antes de la Semana 1.</p>
                        <x-input-error :messages="$errors->get('grabacionSemana')" class="mt-1" />
                    </div>
                    @if ($grabacionTipo === 'archivo')
                        <div>
                            <x-input-label for="grabacionArchivo" value="Archivo de video" />
                            <input wire:model="grabacionArchivo" id="grabacionArchivo" type="file" accept="video/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                            <x-input-error :messages="$errors->get('grabacionArchivo')" class="mt-1" />
                        </div>
                    @elseif ($grabacionTipo !== '')
                        <div>
                            <x-input-label for="grabacionUrl" value="URL" />
                            <x-text-input wire:model="grabacionUrl" id="grabacionUrl" class="mt-1 block w-full" placeholder="https://…" />
                            <x-input-error :messages="$errors->get('grabacionUrl')" class="mt-1" />
                        </div>
                    @endif

                    <x-aula-virtual.checklist-secciones :secciones="$cursosRelacionados" campo="grabacionCursosSeleccionados" />

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormGrabacion', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($clasesGrabadasPorSemana as $numeroSemana => $clasesDeSemana)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                            @foreach ($clasesDeSemana as $claseGrabada)
                                <div class="flex items-center justify-between px-4 py-3 text-sm">
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink-faint">{{ $claseGrabada->tipo->label() }}</span>
                                        <span class="text-ink">{{ $claseGrabada->titulo }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @if ($claseGrabada->tipo->requiereArchivo() && $claseGrabada->getFirstMedia('video'))
                                            <a href="{{ $claseGrabada->getFirstMediaUrl('video') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver video</a>
                                        @elseif ($claseGrabada->url)
                                            <a href="{{ $claseGrabada->url }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Abrir enlace</a>
                                        @endif
                                        @can('manage', $curso)
                                            <button x-on:click="$store.confirm.preguntar('¿Eliminar esta clase grabada?', () => $wire.eliminarGrabacion({{ $claseGrabada->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })" class="text-xs font-medium text-danger hover:underline">Eliminar</button>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay clases grabadas.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Tareas --}}
    @if ($tab === 'tareas')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormTarea', true)">+ Nueva tarea</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormTarea)
                <form wire:submit="crearTarea" class="rounded-2xl border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div>
                        <x-input-label for="tareaTitulo" value="Título" />
                        <x-text-input wire:model="tareaTitulo" id="tareaTitulo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('tareaTitulo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tareaDescripcion" value="Descripción" />
                        <textarea wire:model="tareaDescripcion" id="tareaDescripcion" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="tareaFechaLimite" value="Fecha límite" />
                            <x-text-input wire:model="tareaFechaLimite" id="tareaFechaLimite" type="datetime-local" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('tareaFechaLimite')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="tareaPuntajeMax" value="Puntaje máximo" />
                            <x-text-input wire:model="tareaPuntajeMax" id="tareaPuntajeMax" type="number" min="1" max="20" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="tareaSemana" value="Semana (opcional)" />
                        <x-text-input wire:model="tareaSemana" id="tareaSemana" type="number" min="1" class="mt-1 block w-full" placeholder="Ej. 1" />
                        <p class="mt-1 text-xs text-ink-faint">Déjalo vacío para que aparezca en «Bienvenida», antes de la Semana 1.</p>
                        <x-input-error :messages="$errors->get('tareaSemana')" class="mt-1" />
                    </div>

                    <x-aula-virtual.checklist-secciones :secciones="$cursosRelacionados" campo="tareaCursosSeleccionados" />

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormTarea', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($tareasPorSemana as $numeroSemana => $tareasDeSemana)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                            @foreach ($tareasDeSemana as $tarea)
                                <a href="{{ route('aula-virtual.tarea', [$curso, $tarea]) }}" wire:navigate class="flex items-center justify-between px-4 py-3 text-sm hover:bg-surface-2">
                                    <div>
                                        <p class="text-ink">{{ $tarea->titulo }}</p>
                                        <p class="text-xs text-ink-faint">Vence {{ $tarea->fecha_limite->format('d/m/Y H:i') }} · {{ $tarea->puntaje_max }} pts</p>
                                    </div>
                                    @if ($tarea->estaVencida())
                                        <x-badge variant="danger">Vencida</x-badge>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay tareas.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Publicaciones --}}
    @if ($tab === 'publicaciones')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormPublicacion', true)">+ Nueva publicación</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormPublicacion)
                <form wire:submit="crearPublicacion" class="rounded-2xl border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div>
                        <x-input-label for="publicacionTipo" value="Tipo" />
                        <x-select-input
                            wire:model="publicacionTipo"
                            id="publicacionTipo"
                            class="mt-1 block w-full"
                            :options="collect($tiposPublicacion)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                        />
                        <x-input-error :messages="$errors->get('publicacionTipo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="publicacionContenido" value="Contenido" />
                        <textarea wire:model="publicacionContenido" id="publicacionContenido" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                        <x-input-error :messages="$errors->get('publicacionContenido')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormPublicacion', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Publicar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($publicaciones as $publicacion)
                    <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                        <div class="flex items-center justify-between">
                            <x-badge variant="accent">{{ $publicacion->tipo->label() }}</x-badge>
                            <span class="text-xs text-ink-faint">{{ $publicacion->autor->name }} · {{ $publicacion->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-3 text-sm text-ink">{{ $publicacion->contenido }}</p>

                        <div class="mt-4 space-y-2 border-t border-border pt-3">
                            @foreach ($publicacion->comentarios as $comentario)
                                <div class="text-xs">
                                    <span class="font-medium text-ink">{{ $comentario->autor->name }}</span>
                                    <span class="text-ink-dim">{{ $comentario->contenido }}</span>
                                </div>
                            @endforeach

                            <form wire:submit="comentar({{ $publicacion->id }})" class="flex gap-2 pt-1">
                                <input
                                    type="text"
                                    wire:model="nuevoComentario.{{ $publicacion->id }}"
                                    placeholder="Escribe un comentario…"
                                    class="flex-1 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                >
                                <button type="submit" class="text-xs font-medium text-accent hover:underline">Comentar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-ink-faint">Todavía no hay publicaciones.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Foros --}}
    @if ($tab === 'foros')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormForo', true)">+ Nuevo foro</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormForo)
                <form wire:submit="crearForo" class="rounded-2xl border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div>
                        <x-input-label for="foroTitulo" value="Título" />
                        <x-text-input wire:model="foroTitulo" id="foroTitulo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('foroTitulo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="foroDescripcion" value="Descripción" />
                        <textarea wire:model="foroDescripcion" id="foroDescripcion" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                    <div>
                        <x-input-label for="foroSemana" value="Semana (opcional)" />
                        <x-text-input wire:model="foroSemana" id="foroSemana" type="number" min="1" class="mt-1 block w-full" placeholder="Ej. 1" />
                        <p class="mt-1 text-xs text-ink-faint">Déjalo vacío para que aparezca en «Bienvenida», antes de la Semana 1.</p>
                        <x-input-error :messages="$errors->get('foroSemana')" class="mt-1" />
                    </div>

                    <x-aula-virtual.checklist-secciones :secciones="$cursosRelacionados" campo="foroCursosSeleccionados" />

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormForo', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Crear foro</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($forosPorSemana as $numeroSemana => $forosDeSemana)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $numeroSemana === 0 ? 'Bienvenida' : 'Semana '.$numeroSemana }}</p>
                        <div class="space-y-4">
                            @foreach ($forosDeSemana as $foro)
                                <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                                    <p class="text-ink">{{ $foro->titulo }}</p>
                                    <p class="text-xs text-ink-faint">
                                        {{ $foro->autor->name }} · {{ $foro->respuestas->count() }} {{ Str::plural('respuesta', $foro->respuestas->count()) }}
                                    </p>
                                    @if ($foro->descripcion)
                                        <p class="mt-2 text-sm text-ink-dim">{{ $foro->descripcion }}</p>
                                    @endif

                                    <div class="mt-4 space-y-2 border-t border-border pt-3">
                                        @foreach ($foro->respuestas as $respuesta)
                                            <div class="text-xs">
                                                <span class="font-medium text-ink">{{ $respuesta->autor->name }}</span>
                                                <span class="text-ink-dim">{{ $respuesta->contenido }}</span>
                                            </div>
                                        @endforeach

                                        <form wire:submit="responderForo({{ $foro->id }})" class="flex gap-2 pt-1">
                                            <input
                                                type="text"
                                                wire:model="nuevaRespuestaForo.{{ $foro->id }}"
                                                placeholder="Escribe una respuesta…"
                                                class="flex-1 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                            >
                                            <button type="submit" class="text-xs font-medium text-accent hover:underline">Responder</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-ink-faint">Todavía no hay foros.</p>
                @endforelse
            </div>
        </div>
    @endif

    @can('manage', $curso)
        <x-modal name="guardar-plantilla" focusable>
            <div class="p-6">
                <h2 class="font-display text-lg text-ink">Guardar como plantilla</h2>
                <p class="mt-1 text-sm text-ink-dim">
                    Se guardará una copia de los materiales, clases grabadas, tareas y foros actuales (con sus archivos) para reutilizarlos en otro ciclo de este curso.
                </p>
                <form wire:submit="guardarPlantilla" class="mt-4 space-y-3">
                    <div>
                        <x-input-label for="nombrePlantilla" value="Nombre de la plantilla" />
                        <x-text-input wire:model="nombrePlantilla" id="nombrePlantilla" class="mt-1 block w-full" placeholder="Ej. Plantilla estándar" />
                        <x-input-error :messages="$errors->get('nombrePlantilla')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar plantilla</x-primary-button>
                    </div>
                </form>
            </div>
        </x-modal>

        <x-modal name="aplicar-plantilla" focusable max-width="lg">
            <div class="p-6">
                <h2 class="font-display text-lg text-ink">Aplicar plantilla</h2>
                <p class="mt-1 text-sm text-ink-dim">
                    Se agrega el contenido de la plantilla elegida a este curso; no se borra lo que ya existe. La fecha límite de las tareas se recalcula según el inicio de este ciclo.
                </p>
                <div class="mt-4 max-h-80 divide-y divide-border overflow-y-auto rounded-lg border border-border">
                    @forelse ($plantillasDisponibles as $plantilla)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <p class="text-ink">{{ $plantilla->nombre }}</p>
                                <p class="text-xs text-ink-faint">{{ $plantilla->creador?->name ?? 'Usuario eliminado' }} · {{ $plantilla->created_at->format('d/m/Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" x-on:click="$store.confirm.preguntar(@js('¿Aplicar «'.$plantilla->nombre.'» a este curso?'), () => $wire.aplicarPlantilla({{ $plantilla->id }}), { etiquetaConfirmar: 'Aplicar' })" class="text-xs font-medium text-accent hover:underline">
                                    Aplicar
                                </button>
                                <button type="button" x-on:click="$store.confirm.preguntar(@js('¿Eliminar la plantilla «'.$plantilla->nombre.'»?'), () => $wire.eliminarPlantilla({{ $plantilla->id }}), { peligro: true, etiquetaConfirmar: 'Eliminar' })" class="text-xs font-medium text-danger hover:underline">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay plantillas guardadas para este curso.</p>
                    @endforelse
                </div>
                <div class="mt-4 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Cerrar</x-secondary-button>
                </div>
            </div>
        </x-modal>
    @endcan
</div>
