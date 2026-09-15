<?php

use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Enums\EstadoTramiteEnum;
use App\Modules\Tramites\Models\SolicitudTramite;
use App\Modules\Tramites\Services\TramiteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Una sola página, distinta según permisos (mismo criterio que
 * Incidencias): quien solo tiene tramites.crear/ver_propio ve su propia
 * lista y el formulario de nueva solicitud; quien tiene
 * tramites.gestionar ve todas, con filtros y el panel de resolución.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public bool $mostrarFormNueva = false;

    public string $categoria = '';

    public string $asunto = '';

    public string $descripcion = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $adjuntos = [];

    public string $filtroEstado = '';

    public string $filtroCategoria = '';

    public ?int $tramiteEnGestionId = null;

    public string $nuevoEstado = '';

    public string $resolucion = '';

    public function mount(): void
    {
        abort_unless(
            Auth::user()->hasAnyPermission(['tramites.crear', 'tramites.ver_propio', 'tramites.gestionar']),
            403,
        );
    }

    public function crear(TramiteService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('tramites.crear'), 403);

        $this->validate([
            'categoria' => 'required|string|in:'.implode(',', array_column(CategoriaTramiteEnum::cases(), 'value')),
            'asunto' => 'required|string|max:150',
            'descripcion' => 'required|string|max:2000',
            'adjuntos.*' => 'nullable|file|max:4096',
        ]);

        $service->registrar(
            Auth::user(),
            CategoriaTramiteEnum::from($this->categoria),
            $this->asunto,
            $this->descripcion,
            $this->adjuntos,
        );

        $this->reset(['categoria', 'asunto', 'descripcion', 'adjuntos', 'mostrarFormNueva']);
        session()->flash('status', 'Trámite registrado.');
    }

    public function abrirGestion(int $tramiteId): void
    {
        abort_unless(Auth::user()->hasPermissionTo('tramites.gestionar'), 403);

        $tramite = SolicitudTramite::query()->findOrFail($tramiteId);
        $this->tramiteEnGestionId = $tramiteId;
        $this->nuevoEstado = $tramite->estado->value;
        $this->resolucion = (string) $tramite->resolucion;
    }

    public function cerrarGestion(): void
    {
        $this->reset(['tramiteEnGestionId', 'nuevoEstado', 'resolucion']);
        $this->resetErrorBag();
    }

    public function guardarEstado(TramiteService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('tramites.gestionar'), 403);

        $this->validate([
            'nuevoEstado' => 'required|string|in:'.implode(',', array_column(EstadoTramiteEnum::cases(), 'value')),
            'resolucion' => 'nullable|string|max:2000',
        ]);

        $tramite = SolicitudTramite::query()->findOrFail($this->tramiteEnGestionId);

        $service->actualizarEstado(
            $tramite,
            EstadoTramiteEnum::from($this->nuevoEstado),
            Auth::user(),
            $this->resolucion !== '' ? $this->resolucion : null,
        );

        $this->cerrarGestion();
        session()->flash('status', 'Trámite actualizado.');
    }

    public function with(TramiteService $service): array
    {
        $user = Auth::user();
        $puedeGestionar = $user->hasPermissionTo('tramites.gestionar');
        $puedeCrear = $user->hasPermissionTo('tramites.crear');

        $tramites = $puedeGestionar
            ? $service->todos(
                $this->filtroEstado !== '' ? EstadoTramiteEnum::from($this->filtroEstado) : null,
                $this->filtroCategoria !== '' ? CategoriaTramiteEnum::from($this->filtroCategoria) : null,
            )
            : $service->misTramites($user);

        return [
            'puedeGestionar' => $puedeGestionar,
            'puedeCrear' => $puedeCrear,
            'tramites' => $tramites,
            'categorias' => CategoriaTramiteEnum::cases(),
            'estados' => EstadoTramiteEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Trámites</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($puedeGestionar)
                Todas las solicitudes presentadas por estudiantes, apoderados, docentes y personal.
            @else
                Tus solicitudes administrativas y su resolución.
            @endif
        </p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($puedeCrear)
        <div class="mb-6">
            @unless ($mostrarFormNueva)
                <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', true)">+ Nuevo trámite</x-secondary-button>
            @endunless

            @if ($mostrarFormNueva)
                <form wire:submit="crear" class="mt-3 max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
                    <div>
                        <x-input-label for="categoria" value="Categoría" />
                        <x-select-input
                            wire:model="categoria"
                            id="categoria"
                            class="mt-1 block w-full"
                            :options="collect($categorias)->mapWithKeys(fn ($c) => [$c->value => $c->label()])"
                        />
                        <x-input-error :messages="$errors->get('categoria')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="asunto" value="Asunto" />
                        <x-text-input wire:model="asunto" id="asunto" class="mt-1 block w-full" placeholder="En pocas palabras, qué necesitas" />
                        <x-input-error :messages="$errors->get('asunto')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="descripcion" value="Descripción" />
                        <textarea wire:model="descripcion" id="descripcion" rows="4" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent" placeholder="Cuéntanos con detalle qué necesitas y por qué"></textarea>
                        <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="adjuntos" value="Adjuntos (opcional)" />
                        <input type="file" wire:model="adjuntos" id="adjuntos" multiple class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('adjuntos')" class="mt-1" />
                        <x-input-error :messages="$errors->get('adjuntos.*')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="crear">Registrar</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if ($puedeGestionar)
        <div class="mb-4 flex flex-wrap gap-3">
            <x-select-input
                wire:model.live="filtroEstado"
                class="block w-auto"
                :options="collect(['' => 'Todos los estados'])->union(collect($estados)->mapWithKeys(fn ($e) => [$e->value => $e->label()]))"
            />
            <x-select-input
                wire:model.live="filtroCategoria"
                class="block w-auto"
                :options="collect(['' => 'Todas las categorías'])->union(collect($categorias)->mapWithKeys(fn ($c) => [$c->value => $c->label()]))"
            />
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($tramites as $tramite)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-ink">{{ $tramite->asunto }}</span>
                            <x-badge variant="neutral">{{ $tramite->categoria->label() }}</x-badge>
                            <x-badge :variant="$tramite->estado->variantePildora()">{{ $tramite->estado->label() }}</x-badge>
                        </div>
                        @if ($puedeGestionar)
                            <p class="mt-1 text-xs text-ink-faint">
                                {{ $tramite->solicitante?->name ?? 'Usuario eliminado' }} · {{ $tramite->created_at->format('d/m/Y H:i') }}
                            </p>
                        @else
                            <p class="mt-1 text-xs text-ink-faint">{{ $tramite->created_at->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>

                    @if ($puedeGestionar && $tramiteEnGestionId !== $tramite->id)
                        <x-secondary-button type="button" wire:click="abrirGestion({{ $tramite->id }})">Gestionar</x-secondary-button>
                    @endif
                </div>

                <p class="mt-2 text-sm text-ink-dim">{{ $tramite->descripcion }}</p>

                @if ($tramite->getMedia('adjuntos')->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($tramite->getMedia('adjuntos') as $adjunto)
                            <a href="{{ $adjunto->getUrl() }}" target="_blank" class="text-xs font-medium text-accent hover:underline">{{ $adjunto->file_name }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($tramite->resolucion)
                    <div class="mt-3 rounded-md bg-surface-2 p-3 text-sm text-ink">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Resolución</p>
                        <p class="mt-1">{{ $tramite->resolucion }}</p>
                        @if ($tramite->responsable)
                            <p class="mt-1 text-xs text-ink-faint">{{ $tramite->responsable->name }}@if ($tramite->atendido_en) · {{ $tramite->atendido_en->format('d/m/Y H:i') }} @endif</p>
                        @endif
                    </div>
                @endif

                @if ($puedeGestionar && $tramiteEnGestionId === $tramite->id)
                    <form wire:submit="guardarEstado" class="mt-3 space-y-3 rounded-md border border-border bg-surface-2 p-4">
                        <div>
                            <x-input-label for="nuevoEstado" value="Nuevo estado" />
                            <x-select-input
                                wire:model="nuevoEstado"
                                id="nuevoEstado"
                                class="mt-1 block w-full sm:w-64"
                                :options="collect($estados)->mapWithKeys(fn ($e) => [$e->value => $e->label()])"
                            />
                            <x-input-error :messages="$errors->get('nuevoEstado')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="resolucion" value="Resolución" />
                            <textarea wire:model="resolucion" id="resolucion" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent" placeholder="Explica el motivo o el resultado"></textarea>
                            <x-input-error :messages="$errors->get('resolucion')" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" wire:click="cerrarGestion">Cancelar</x-secondary-button>
                            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="guardarEstado">Guardar</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
                @if ($puedeGestionar)
                    No hay trámites que coincidan con el filtro.
                @else
                    Todavía no has presentado ningún trámite.
                @endif
            </p>
        @endforelse
    </div>
</div>
