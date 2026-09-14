<?php

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\EntregaTarea;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public CursoVirtual $curso;

    public Tarea $tarea;

    public string $comentario = '';

    public $archivo = null;

    /** @var array<int, string> */
    public array $notas = [];

    public function mount(CursoVirtual $curso, Tarea $tarea): void
    {
        Gate::authorize('view', $curso);

        abort_unless($tarea->curso_virtual_id === $curso->id, 404);

        $this->curso = $curso;
        $this->tarea = $tarea;

        $miEntrega = $this->miEntrega();

        if ($miEntrega) {
            $this->comentario = (string) $miEntrega->comentario;
        }
    }

    private function miEntrega(): ?EntregaTarea
    {
        $estudiante = Auth::user()->estudiante;

        if (! $estudiante) {
            return null;
        }

        return app(TareaService::class)->entregaDe($this->tarea, $estudiante);
    }

    public function entregar(TareaService $service, BloqueoAccesoService $bloqueos): void
    {
        $estudiante = Auth::user()->estudiante;

        abort_unless($estudiante !== null, 403);
        abort_if($bloqueos->tieneCuotasVencidasEnCicloActual($estudiante), 403);

        $this->validate([
            'comentario' => 'nullable|string',
            'archivo' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png|max:10240',
        ]);

        $service->entregar($this->tarea, $estudiante, $this->comentario ?: null, $this->archivo);

        $this->reset('archivo');
    }

    public function calificar(int $entregaId, TareaService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $nota = (float) ($this->notas[$entregaId] ?? 0);

        $this->validate([
            'notas.'.$entregaId => 'required|numeric|min:0|max:'.$this->tarea->puntaje_max,
        ]);

        $service->calificar(EntregaTarea::query()->where('tarea_id', $this->tarea->id)->findOrFail($entregaId), $nota);
    }

    public function with(BloqueoAccesoService $bloqueos): array
    {
        $puedeGestionar = Gate::allows('manage', $this->curso);
        $estudiante = Auth::user()->estudiante;

        return [
            'puedeGestionar' => $puedeGestionar,
            'entregas' => $puedeGestionar
                ? $this->tarea->entregas()->with('estudiante')->latest('fecha_entrega')->get()
                : collect(),
            'miEntrega' => $puedeGestionar ? null : $this->miEntrega(),
            'estaBloqueado' => ! $puedeGestionar && $estudiante && $bloqueos->tieneCuotasVencidasEnCicloActual($estudiante),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('aula-virtual.show', $curso) }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← {{ $curso->horario->curso->nombre }}</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $tarea->titulo }}</h1>
        <p class="mt-1 text-sm text-ink-dim">Vence {{ $tarea->fecha_limite->format('d/m/Y H:i') }} · {{ $tarea->puntaje_max }} pts</p>
    </x-slot>

    @if ($tarea->descripcion)
        <div class="mb-6 rounded-2xl border border-border bg-surface shadow-sm p-4 text-sm text-ink-dim">
            {{ $tarea->descripcion }}
        </div>
    @endif

    @if ($puedeGestionar)
        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
            @forelse ($entregas as $entrega)
                <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $entrega->estudiante?->nombreCompleto() ?? $entrega->estudiante_id }}</p>
                        <p class="text-xs text-ink-faint">
                            {{ $entrega->estado->label() }}
                            @if ($entrega->fecha_entrega)
                                · {{ $entrega->fecha_entrega->format('d/m/Y H:i') }}
                            @endif
                        </p>
                        @if ($entrega->comentario)
                            <p class="mt-1 text-xs text-ink-dim">{{ $entrega->comentario }}</p>
                        @endif
                        @if ($entrega->getFirstMedia('archivo'))
                            <a href="{{ $entrega->getFirstMediaUrl('archivo') }}" target="_blank" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">Ver archivo entregado</a>
                        @endif
                    </div>
                    <form wire:submit="calificar({{ $entrega->id }})" class="flex items-center gap-2">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="{{ $tarea->puntaje_max }}"
                            wire:model="notas.{{ $entrega->id }}"
                            placeholder="{{ $entrega->nota ?? '—' }}"
                            class="w-20 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"
                        >
                        <x-secondary-button type="submit">Calificar</x-secondary-button>
                    </form>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay entregas.</p>
            @endforelse
        </div>
    @else
        <div class="rounded-2xl border border-border bg-surface shadow-sm p-4">
            @if ($miEntrega && $miEntrega->estado === \App\Modules\AulaVirtual\Enums\EstadoEntregaEnum::CALIFICADO)
                <p class="text-sm text-ink">Tu entrega ya fue calificada.</p>
                <p class="mt-2 font-display text-3xl text-accent">{{ $miEntrega->nota }} <span class="text-base text-ink-faint">/ {{ $tarea->puntaje_max }}</span></p>
                @if ($miEntrega->comentario)
                    <p class="mt-3 text-sm text-ink-dim">{{ $miEntrega->comentario }}</p>
                @endif
                @if ($miEntrega->getFirstMedia('archivo'))
                    <a href="{{ $miEntrega->getFirstMediaUrl('archivo') }}" target="_blank" class="mt-2 inline-block text-xs font-medium text-accent hover:underline">Ver mi archivo entregado</a>
                @endif
            @elseif ($estaBloqueado)
                <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-4 text-sm text-danger">
                    <p class="font-medium">No puedes entregar esta tarea por ahora.</p>
                    <p class="mt-1">Tienes cuotas vencidas del ciclo actual. Regulariza tu deuda en
                        <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                        o comunícate con Cobranza para un compromiso de pago.</p>
                </div>
            @else
                @if ($miEntrega)
                    <p class="mb-4 text-xs text-ink-faint">
                        Ya entregaste esta tarea ({{ $miEntrega->estado->label() }}). Puedes volver a enviarla mientras no esté calificada.
                    </p>
                @endif

                <form wire:submit="entregar" class="space-y-3">
                    <div>
                        <x-input-label for="comentario" value="Comentario (opcional)" />
                        <textarea wire:model="comentario" id="comentario" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                    <div>
                        <x-input-label for="archivo" value="Archivo (opcional)" />
                        <input wire:model="archivo" id="archivo" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('archivo')" class="mt-1" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button type="submit">{{ $miEntrega ? 'Actualizar entrega' : 'Entregar' }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</div>
