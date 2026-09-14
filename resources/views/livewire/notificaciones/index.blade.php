<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use App\Modules\Notificaciones\Services\CampaniaWhatsappService;
use App\Modules\Notificaciones\Services\MensajeWhatsappService;
use App\Modules\Notificaciones\Services\PlantillaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $tab = 'nueva';

    public string $filtroTipo = '';

    public string $filtroEstado = '';

    public string $nombre = '';

    public string $plantillaId = '';

    public string $gradoId = '';

    public string $cicloId = '';

    public bool $soloConDeuda = false;

    public function mount(): void
    {
        Gate::authorize('whatsapp.enviar');
    }

    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{grado_id?: int, ciclo_id?: int, solo_con_deuda?: bool}
     */
    private function segmentoActual(): array
    {
        $segmento = [];

        if ($this->gradoId !== '') {
            $segmento['grado_id'] = (int) $this->gradoId;
        }

        if ($this->cicloId !== '') {
            $segmento['ciclo_id'] = (int) $this->cicloId;
        }

        if ($this->soloConDeuda) {
            $segmento['solo_con_deuda'] = true;
        }

        return $segmento;
    }

    public function enviarCampania(CampaniaWhatsappService $service): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'plantillaId' => 'required|integer|exists:plantillas_whatsapp,id',
        ]);

        $service->crearYEnviar(
            $this->nombre,
            PlantillaWhatsapp::query()->findOrFail($this->plantillaId),
            $this->segmentoActual(),
            Auth::user(),
        );

        $this->reset(['nombre', 'plantillaId', 'gradoId', 'cicloId', 'soloConDeuda']);
        $this->tab = 'historial';
        session()->flash('status', 'Campaña creada. Los mensajes se están enviando.');
    }

    public function with(CampaniaWhatsappService $campanias, PlantillaService $plantillas, MensajeWhatsappService $mensajes): array
    {
        return [
            'plantillasActivas' => $plantillas->activas(),
            'grados' => Grado::query()->orderBy('orden')->get(),
            'ciclos' => Ciclo::query()->latest('fecha_inicio')->get(),
            'destinatariosPrevistos' => $campanias->resolverDestinatarios($this->segmentoActual())->count(),
            'campanias' => $campanias->todas(),
            'mensajes' => $mensajes->listar([
                'tipo' => $this->filtroTipo ?: null,
                'estado' => $this->filtroEstado ?: null,
            ]),
            'tipos' => TipoMensajeWhatsappEnum::cases(),
            'estados' => EstadoMensajeWhatsappEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Notificaciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Envíos masivos de WhatsApp segmentados por grado, ciclo o estado de deuda.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        <button wire:click="$set('tab', 'nueva')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'nueva', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'nueva'])>
            Nueva campaña
        </button>
        <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
            Historial
        </button>
        <button wire:click="$set('tab', 'mensajes')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'mensajes', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'mensajes'])>
            Mensajes
        </button>
        <a href="{{ route('notificaciones.plantillas') }}" wire:navigate class="ml-auto self-center text-sm font-medium text-accent hover:underline">
            Gestionar plantillas →
        </a>
    </div>

    {{-- Nueva campaña --}}
    @if ($tab === 'nueva')
        <div class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div>
                <x-input-label for="nombre" value="Nombre de la campaña" />
                <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" placeholder="Ej. Recordatorio de cuotas — julio" />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="plantillaId" value="Plantilla" />
                <x-select-input
                    wire:model="plantillaId"
                    id="plantillaId"
                    class="mt-1 block w-full"
                    :options="collect($plantillasActivas)->mapWithKeys(fn ($plantilla) => [$plantilla->id => $plantilla->nombre])"
                />
                <x-input-error :messages="$errors->get('plantillaId')" class="mt-1" />
                @if ($plantillasActivas->isEmpty())
                    <p class="mt-1 text-xs text-warn">No hay plantillas activas. <a href="{{ route('notificaciones.plantillas') }}" wire:navigate class="underline">Crea una primero</a>.</p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="gradoId" value="Grado (opcional)" />
                    <x-select-input
                        wire:model.live="gradoId"
                        id="gradoId"
                        class="mt-1 block w-full"
                        :options="collect($grados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Todos los grados', '')"
                    />
                </div>
                <div>
                    <x-input-label for="cicloId" value="Ciclo (opcional)" />
                    <x-select-input
                        wire:model.live="cicloId"
                        id="cicloId"
                        class="mt-1 block w-full"
                        :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])->prepend('Todos los ciclos', '')"
                    />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-dim">
                <input type="checkbox" wire:model.live="soloConDeuda" class="rounded border-border text-accent focus:ring-accent">
                Solo estudiantes con deuda bloqueante activa
            </label>

            <div class="rounded-md bg-surface-2 px-3 py-2 text-sm text-ink-dim">
                Este segmento alcanza a <span class="font-semibold text-ink">{{ $destinatariosPrevistos }}</span>
                {{ $destinatariosPrevistos === 1 ? 'estudiante' : 'estudiantes' }}.
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" x-on:click="$store.confirm.preguntar('¿Enviar esta campaña a {{ $destinatariosPrevistos }} destinatarios?', () => $wire.enviarCampania(), { etiquetaConfirmar: 'Enviar' })">
                    Enviar campaña
                </x-primary-button>
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial')
        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
            @forelse ($campanias as $campania)
                <div class="px-4 py-3 text-sm" wire:key="campania-{{ $campania->id }}">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $campania->nombre }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $campania->plantilla->nombre }} · creada por {{ $campania->creador->name }}
                                · {{ $campania->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-ok/10 text-ok' => $campania->estado->value === 'completada',
                            'bg-warn/10 text-warn' => $campania->estado->value === 'enviando',
                            'bg-ink-faint/10 text-ink-faint' => $campania->estado->value === 'borrador',
                        ])>{{ $campania->estado->label() }}</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-faint">
                        {{ $campania->enviados }} enviados · {{ $campania->fallidos }} fallidos
                        de {{ $campania->total_destinatarios }} destinatarios
                    </p>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No se han enviado campañas todavía.</p>
            @endforelse
        </div>
    @endif

    {{-- Mensajes --}}
    @if ($tab === 'mensajes')
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-select-input
                    wire:model.live="filtroTipo"
                    class="w-full sm:max-w-xs"
                    :options="collect($tipos)->mapWithKeys(fn ($tipoOpcion) => [$tipoOpcion->value => $tipoOpcion->label()])->prepend('Todos los tipos', '')"
                />

                <x-select-input
                    wire:model.live="filtroEstado"
                    class="w-full sm:max-w-xs"
                    :options="collect($estados)->mapWithKeys(fn ($estadoOpcion) => [$estadoOpcion->value => $estadoOpcion->label()])->prepend('Todos los estados', '')"
                />
            </div>

            <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-surface-2">
                        <tr>
                            <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Destinatario</th>
                            <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                            <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Contenido</th>
                            <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                            <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($mensajes as $mensaje)
                            <tr wire:key="mensaje-{{ $mensaje->id }}">
                                <td class="px-4 py-3 text-ink">
                                    {{ $mensaje->estudiante?->nombreCompleto() ?? '—' }}
                                    <span class="block font-mono text-xs text-ink-faint">{{ $mensaje->telefono }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-info/10 text-info' => $mensaje->tipo->value === 'campania',
                                        'bg-warn/10 text-warn' => $mensaje->tipo->value === 'recordatorio',
                                        'bg-accent-soft text-accent' => $mensaje->tipo->value === 'entrante',
                                    ])>{{ $mensaje->tipo->label() }}</span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-ink-dim" title="{{ $mensaje->contenido }}">{{ Str::limit($mensaje->contenido, 60) }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-warn/10 text-warn' => $mensaje->estado->value === 'pendiente',
                                        'bg-info/10 text-info' => in_array($mensaje->estado->value, ['enviado', 'recibido']),
                                        'bg-ok/10 text-ok' => in_array($mensaje->estado->value, ['entregado', 'leido']),
                                        'bg-danger/10 text-danger' => $mensaje->estado->value === 'fallido',
                                    ])>{{ $mensaje->estado->label() }}</span>
                                    @if ($mensaje->estado->value === 'fallido' && $mensaje->error)
                                        <span class="block text-xs text-danger" title="{{ $mensaje->error }}">{{ Str::limit($mensaje->error, 40) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-faint" title="{{ $mensaje->created_at?->format('d/m/Y H:i') }}">
                                    {{ ($mensaje->enviado_en ?? $mensaje->created_at)?->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay mensajes con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $mensajes->links() }}
            </div>
        </div>
    @endif
</div>
