<?php

use App\Models\User;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Libro;
use App\Modules\Biblioteca\Models\Prestamo;
use App\Modules\Biblioteca\Services\BibliotecaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Catálogo, visible para cualquiera con biblioteca.ver. Quien además
 * tiene biblioteca.gestionar ve, en esta misma página, cómo agregar
 * libros/ejemplares y la lista de préstamos activos con prestar/devolver/
 * marcar perdido. Dos listas paginadas por separado (catálogo y préstamos
 * activos), cada una con su propio nombre de página -- si compartieran el
 * mismo parámetro ?page=, paginar una movería la otra.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public bool $mostrarFormLibro = false;

    public string $titulo = '';

    public string $autor = '';

    public string $isbn = '';

    public string $categoria = '';

    public string $editorial = '';

    public string $anioPublicacion = '';

    public ?int $libroEnGestionId = null;

    public string $codigoInventario = '';

    public ?int $ejemplarEnPrestamoId = null;

    public string $dniSolicitante = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.ver'), 403);
    }

    public function updatingTermino(): void
    {
        $this->resetPage('librosPage');
    }

    public function abrirFormLibro(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->reset(['titulo', 'autor', 'isbn', 'categoria', 'editorial', 'anioPublicacion']);
        $this->mostrarFormLibro = true;
    }

    public function guardarLibro(BibliotecaService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->validate([
            'titulo' => 'required|string|max:200',
            'autor' => 'required|string|max:150',
            'isbn' => 'nullable|string|max:20',
            'categoria' => 'nullable|string|max:80',
            'editorial' => 'nullable|string|max:120',
            'anioPublicacion' => 'nullable|integer|min:1400|max:'.(now()->year + 1),
        ]);

        $service->registrarLibro(
            $this->titulo,
            $this->autor,
            $this->isbn !== '' ? $this->isbn : null,
            $this->categoria !== '' ? $this->categoria : null,
            $this->editorial !== '' ? $this->editorial : null,
            $this->anioPublicacion !== '' ? (int) $this->anioPublicacion : null,
        );

        $this->mostrarFormLibro = false;
        session()->flash('status', 'Libro agregado al catálogo.');
    }

    public function abrirFormEjemplar(int $libroId): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->libroEnGestionId = $libroId;
        $this->codigoInventario = '';
    }

    public function cerrarFormEjemplar(): void
    {
        $this->reset(['libroEnGestionId', 'codigoInventario']);
        $this->resetErrorBag();
    }

    public function guardarEjemplar(BibliotecaService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->validate([
            'codigoInventario' => 'required|string|max:40|unique:ejemplares,codigo_inventario',
        ]);

        $libro = Libro::query()->findOrFail($this->libroEnGestionId);
        $service->agregarEjemplar($libro, $this->codigoInventario);

        $this->cerrarFormEjemplar();
        session()->flash('status', 'Ejemplar agregado.');
    }

    public function abrirFormPrestamo(int $ejemplarId): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->ejemplarEnPrestamoId = $ejemplarId;
        $this->dniSolicitante = '';
    }

    public function cerrarFormPrestamo(): void
    {
        $this->reset(['ejemplarEnPrestamoId', 'dniSolicitante']);
        $this->resetErrorBag();
    }

    public function guardarPrestamo(BibliotecaService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $this->validate(['dniSolicitante' => 'required|string']);

        $solicitante = User::query()->where('dni', $this->dniSolicitante)->first();

        if (! $solicitante) {
            $this->addError('dniSolicitante', 'No hay ninguna cuenta con ese DNI.');

            return;
        }

        $ejemplar = Ejemplar::query()->findOrFail($this->ejemplarEnPrestamoId);
        $service->prestar($ejemplar, $solicitante, Auth::user());

        $this->cerrarFormPrestamo();
        session()->flash('status', 'Préstamo registrado.');
    }

    public function devolver(int $prestamoId, BibliotecaService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $service->devolver(Prestamo::query()->findOrFail($prestamoId));
        session()->flash('status', 'Devolución registrada.');
    }

    public function marcarPerdido(int $prestamoId, BibliotecaService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('biblioteca.gestionar'), 403);

        $service->marcarPerdido(Prestamo::query()->findOrFail($prestamoId));
        session()->flash('status', 'Marcado como perdido.');
    }

    public function with(BibliotecaService $service): array
    {
        $puedeGestionar = Auth::user()->hasPermissionTo('biblioteca.gestionar');

        return [
            'puedeGestionar' => $puedeGestionar,
            'libros' => $service->catalogo($this->termino !== '' ? $this->termino : null),
            'prestamosActivos' => $puedeGestionar ? $service->prestamosActivos() : collect(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Biblioteca</h1>
        <p class="mt-1 text-sm text-ink-dim">Catálogo de libros y control de préstamos.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-text-input wire:model.live.debounce.300ms="termino" type="search" class="w-full sm:max-w-xs" placeholder="Buscar por título, autor o ISBN…" />

        @if ($puedeGestionar)
            <x-secondary-button type="button" wire:click="abrirFormLibro">+ Nuevo libro</x-secondary-button>
        @endif
    </div>

    @if ($mostrarFormLibro)
        <form wire:submit="guardarLibro" class="mb-6 max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div>
                <x-input-label for="titulo" value="Título" />
                <x-text-input wire:model="titulo" id="titulo" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="autor" value="Autor" />
                <x-text-input wire:model="autor" id="autor" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('autor')" class="mt-1" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="isbn" value="ISBN (opcional)" />
                    <x-text-input wire:model="isbn" id="isbn" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label for="categoria" value="Categoría (opcional)" />
                    <x-text-input wire:model="categoria" id="categoria" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="editorial" value="Editorial (opcional)" />
                    <x-text-input wire:model="editorial" id="editorial" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label for="anioPublicacion" value="Año de publicación (opcional)" />
                    <x-text-input wire:model="anioPublicacion" id="anioPublicacion" type="number" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('anioPublicacion')" class="mt-1" />
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" wire:click="$set('mostrarFormLibro', false)">Cancelar</x-secondary-button>
                <x-primary-button type="submit">Guardar</x-primary-button>
            </div>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($libros as $libro)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $libro->titulo }}</p>
                        <p class="text-xs text-ink-faint">{{ $libro->autor }}@if ($libro->categoria) · {{ $libro->categoria }} @endif@if ($libro->anio_publicacion) · {{ $libro->anio_publicacion }} @endif</p>
                    </div>
                    @if ($puedeGestionar)
                        <x-secondary-button type="button" wire:click="abrirFormEjemplar({{ $libro->id }})">+ Ejemplar</x-secondary-button>
                    @endif
                </div>

                @if ($puedeGestionar && $libroEnGestionId === $libro->id)
                    <form wire:submit="guardarEjemplar" class="mt-3 flex flex-wrap items-end gap-2 rounded-md border border-border bg-surface-2 p-3">
                        <div class="flex-1">
                            <x-input-label for="codigoInventario" value="Código de inventario" />
                            <x-text-input wire:model="codigoInventario" id="codigoInventario" class="mt-1 block w-full" placeholder="Ej. BIB-00042" />
                            <x-input-error :messages="$errors->get('codigoInventario')" class="mt-1" />
                        </div>
                        <x-secondary-button type="button" wire:click="cerrarFormEjemplar">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Agregar</x-primary-button>
                    </form>
                @endif

                @if ($libro->ejemplares->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($libro->ejemplares as $ejemplar)
                            <div class="flex items-center gap-2 rounded-md border border-border px-2 py-1">
                                <span class="font-mono text-xs text-ink-dim">{{ $ejemplar->codigo_inventario }}</span>
                                <x-badge variant="{{ $ejemplar->estado->variantePildora() }}">{{ $ejemplar->estado->label() }}</x-badge>
                                @if ($puedeGestionar && $ejemplar->estado->value === 'disponible')
                                    <button type="button" wire:click="abrirFormPrestamo({{ $ejemplar->id }})" class="text-xs font-medium text-accent hover:underline">Prestar</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-xs text-ink-faint">Sin ejemplares registrados.</p>
                @endif

                @if ($puedeGestionar && $ejemplarEnPrestamoId && $libro->ejemplares->contains('id', $ejemplarEnPrestamoId))
                    <form wire:submit="guardarPrestamo" class="mt-3 flex flex-wrap items-end gap-2 rounded-md border border-border bg-surface-2 p-3">
                        <div class="flex-1">
                            <x-input-label for="dniSolicitante" value="DNI de quien se lleva el libro" />
                            <x-text-input wire:model="dniSolicitante" id="dniSolicitante" class="mt-1 block w-full" placeholder="Estudiante o docente con cuenta en el sistema" />
                            <x-input-error :messages="$errors->get('dniSolicitante')" class="mt-1" />
                        </div>
                        <x-secondary-button type="button" wire:click="cerrarFormPrestamo">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Confirmar préstamo</x-primary-button>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">No hay libros en el catálogo todavía.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $libros->links() }}</div>

    @if ($puedeGestionar)
        <h2 class="mb-3 mt-8 font-display text-lg text-ink">Préstamos activos</h2>

        <div class="space-y-2">
            @forelse ($prestamosActivos as $prestamo)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-surface p-3">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $prestamo->ejemplar->libro->titulo }}</p>
                        <p class="text-xs text-ink-faint">
                            {{ $prestamo->solicitante->name }} · vence {{ $prestamo->fecha_devolucion_esperada->format('d/m/Y') }}
                            @if ($prestamo->estaVencido())
                                <x-badge variant="danger">Vencido</x-badge>
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="devolver({{ $prestamo->id }})" class="text-xs font-medium text-accent hover:underline">Devolver</button>
                        <button type="button" wire:click="marcarPerdido({{ $prestamo->id }})" wire:confirm="¿Marcar este ejemplar como perdido?" class="text-xs font-medium text-danger hover:underline">Marcar perdido</button>
                    </div>
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">No hay préstamos activos.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $prestamosActivos->links() }}</div>
    @endif
</div>
