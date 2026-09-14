<?php

use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'solicitudes';

    // Emitir directo
    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public string $tipoDocumentoEmitir = '';

    public string $matriculaId = '';

    public string $observaciones = '';

    // Rechazo de solicitud
    /** @var array<int, string> */
    public array $motivoRechazo = [];

    // Duplicados
    /** @var array<int, string> */
    public array $observacionesDuplicado = [];

    // Plantilla del documento
    public string $plantillaTipo = '';

    public string $plantillaInstitucion = '';

    public string $plantillaTitulo = '';

    public string $plantillaCuerpo = '';

    public string $plantillaPieNota = '';

    public string $plantillaColorAcento = '#137A6C';

    // Marcar entregado (certificado o libreta, con foto opcional)
    public string $marcandoEntregaTipo = '';

    public ?int $marcandoEntregaId = null;

    public $fotoEntrega = null;

    public function mount(CertificadoService $service): void
    {
        $user = Auth::user();

        abort_unless($user->hasAnyPermission(['certificados.ver', 'certificados.emitir']), 403);

        $this->tab = $user->hasPermissionTo('certificados.emitir') ? 'solicitudes' : 'historial';
        $this->tipoDocumentoEmitir = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value;

        if ($user->hasPermissionTo('certificados.gestionar_plantilla')) {
            $this->plantillaTipo = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value;
            $this->cargarPlantilla($service);
        }
    }

    public function updatedPlantillaTipo(CertificadoService $service): void
    {
        $this->cargarPlantilla($service);
    }

    private function cargarPlantilla(CertificadoService $service): void
    {
        $plantilla = $service->plantillaParaTipo(TipoDocumentoEnum::from($this->plantillaTipo));

        $this->plantillaInstitucion = $plantilla->institucion;
        $this->plantillaTitulo = $plantilla->titulo;
        $this->plantillaCuerpo = $plantilla->cuerpo;
        $this->plantillaPieNota = (string) $plantilla->pie_nota;
        $this->plantillaColorAcento = $plantilla->color_acento;
    }

    public function guardarPlantilla(CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.gestionar_plantilla'), 403);

        $this->validate([
            'plantillaInstitucion' => 'required|string|max:150',
            'plantillaTitulo' => 'required|string|max:100',
            'plantillaCuerpo' => 'required|string|max:2000',
            'plantillaPieNota' => 'nullable|string|max:500',
            'plantillaColorAcento' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $service->guardarPlantilla(TipoDocumentoEnum::from($this->plantillaTipo), [
            'institucion' => $this->plantillaInstitucion,
            'titulo' => $this->plantillaTitulo,
            'cuerpo' => $this->plantillaCuerpo,
            'pie_nota' => $this->plantillaPieNota ?: null,
            'color_acento' => $this->plantillaColorAcento,
        ]);

        session()->flash('status', 'Plantilla actualizada.');
    }

    public function previsualizarPlantilla(CertificadoService $service)
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.gestionar_plantilla'), 403);

        $this->validate([
            'plantillaInstitucion' => 'required|string|max:150',
            'plantillaTitulo' => 'required|string|max:100',
            'plantillaCuerpo' => 'required|string|max:2000',
            'plantillaPieNota' => 'nullable|string|max:500',
            'plantillaColorAcento' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $plantilla = new PlantillaCertificado([
            'tipo' => TipoDocumentoEnum::from($this->plantillaTipo),
            'institucion' => $this->plantillaInstitucion,
            'titulo' => $this->plantillaTitulo,
            'cuerpo' => $this->plantillaCuerpo,
            'pie_nota' => $this->plantillaPieNota ?: null,
            'color_acento' => $this->plantillaColorAcento,
        ]);

        $pdf = $service->previsualizarPlantilla($plantilla);

        return response()->streamDownload(
            fn () => print ($pdf),
            'vista-previa-documento.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';
    }

    public function emitir(CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->validate([
            'estudianteSeleccionadoId' => 'required|integer|exists:estudiantes,id',
            'tipoDocumentoEmitir' => 'required|string|in:'.implode(',', array_column(array_filter(TipoDocumentoEnum::conPlantilla(), fn ($tipo) => in_array($tipo, TipoDocumentoEnum::certificados(), true)), 'value')),
            'matriculaId' => 'nullable|integer|exists:matriculas,id',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $estudiante = Estudiante::query()->findOrFail($this->estudianteSeleccionadoId);
        $matricula = $this->matriculaId !== '' ? Matricula::query()->findOrFail($this->matriculaId) : null;

        $service->emitir($estudiante, $matricula, null, $this->observaciones ?: null, Auth::user(), TipoDocumentoEnum::from($this->tipoDocumentoEmitir));

        $this->reset(['estudianteSeleccionadoId', 'estudianteSeleccionadoNombre', 'matriculaId', 'observaciones']);
        $this->tipoDocumentoEmitir = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value;
        session()->flash('status', 'Documento emitido.');
    }

    public function emitirDeSolicitud(int $solicitudId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $solicitud = SolicitudCertificado::query()->findOrFail($solicitudId);

        if ($solicitud->tipo->esLibreta()) {
            $service->emitirLibretaDesdeSolicitud($solicitud, Auth::user());
        } else {
            $service->emitir($solicitud->estudiante, $solicitud->matricula, $solicitud, null, Auth::user());
        }

        session()->flash('status', 'Documento emitido a partir de la solicitud.');
    }

    public function rechazarSolicitud(int $solicitudId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->validate(['motivoRechazo.'.$solicitudId => 'required|string|max:255']);

        $service->rechazarSolicitud(
            SolicitudCertificado::query()->findOrFail($solicitudId),
            $this->motivoRechazo[$solicitudId],
            Auth::user(),
        );

        unset($this->motivoRechazo[$solicitudId]);
        session()->flash('status', 'Solicitud rechazada.');
    }

    public function iniciarEntrega(string $tipo, int $id): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->marcandoEntregaTipo = $tipo;
        $this->marcandoEntregaId = $id;
        $this->fotoEntrega = null;
    }

    public function cancelarEntrega(): void
    {
        $this->marcandoEntregaTipo = '';
        $this->marcandoEntregaId = null;
        $this->fotoEntrega = null;
    }

    public function confirmarEntrega(CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->validate(['fotoEntrega' => 'nullable|image|max:4096']);

        if ($this->marcandoEntregaTipo === 'libreta') {
            $service->marcarLibretaEntregada(Libreta::query()->findOrFail($this->marcandoEntregaId), Auth::user(), $this->fotoEntrega);
        } else {
            $service->marcarEntregado(Certificado::query()->findOrFail($this->marcandoEntregaId), Auth::user(), $this->fotoEntrega);
        }

        $this->cancelarEntrega();
        session()->flash('status', 'Documento marcado como entregado.');
    }

    public function duplicar(int $certificadoId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.duplicar'), 403);

        $service->duplicar(
            Certificado::query()->findOrFail($certificadoId),
            $this->observacionesDuplicado[$certificadoId] ?? null,
            Auth::user(),
        );

        unset($this->observacionesDuplicado[$certificadoId]);
        session()->flash('status', 'Duplicado emitido.');
    }

    public function with(CertificadoService $certificados, LibretaService $libretas): array
    {
        $user = Auth::user();
        $puedeEmitir = $user->hasPermissionTo('certificados.emitir');
        $puedeDuplicar = $user->hasPermissionTo('certificados.duplicar');
        $puedeVerHistorial = $user->hasAnyPermission(['certificados.ver', 'certificados.emitir']);
        $puedeGestionarPlantilla = $user->hasPermissionTo('certificados.gestionar_plantilla');

        $resultadosBusqueda = collect();
        $matriculasDelEstudiante = collect();
        if ($puedeEmitir) {
            if ($this->terminoBusqueda !== '') {
                $resultadosBusqueda = Estudiante::query()
                    ->where(function ($query) {
                        $termino = $this->terminoBusqueda;
                        $query->where('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%")
                            ->orWhere('dni', 'like', "%{$termino}%");
                    })
                    ->limit(8)
                    ->get();
            }

            if ($this->estudianteSeleccionadoId) {
                $matriculasDelEstudiante = Matricula::query()
                    ->where('estudiante_id', $this->estudianteSeleccionadoId)
                    ->with(['grado', 'ciclo'])
                    ->latest('fecha_matricula')
                    ->get();
            }
        }

        // Este módulo es solo para el certificado de estudios (las
        // constancias tienen su propia pantalla, ver constancias/index.blade.php):
        // se filtra por tipo tanto la lista de solicitudes/historial como
        // las opciones de emitir/plantilla. array_intersect() no sirve aquí:
        // compara castejando a string, y un enum de PHP no es convertible a
        // string, así que se filtra a mano con in_array() en modo estricto.
        $tiposDelModulo = TipoDocumentoEnum::certificados();
        $enEsteModulo = fn ($documento) => in_array($documento->tipo, $tiposDelModulo, true);

        return [
            'puedeEmitir' => $puedeEmitir,
            'puedeDuplicar' => $puedeDuplicar,
            'puedeVerHistorial' => $puedeVerHistorial,
            'puedeGestionarPlantilla' => $puedeGestionarPlantilla,
            'tiposDocumentoConPlantilla' => array_values(array_filter(TipoDocumentoEnum::conPlantilla(), fn ($tipo) => in_array($tipo, $tiposDelModulo, true))),
            'solicitudesPendientes' => $puedeEmitir ? $certificados->solicitudesPendientes()->filter($enEsteModulo)->values() : collect(),
            'historial' => $puedeVerHistorial ? $certificados->todos()->filter($enEsteModulo)->values() : collect(),
            'historialLibretas' => $puedeVerHistorial ? $libretas->todas() : collect(),
            'resultadosBusqueda' => $resultadosBusqueda,
            'matriculasDelEstudiante' => $matriculasDelEstudiante,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Certificados</h1>
        <p class="mt-1 text-sm text-ink-dim">Solicitudes de estudiantes, emisión y duplicados de certificados de estudios.</p>
    </x-slot>

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        @if ($puedeEmitir)
            <button wire:click="$set('tab', 'solicitudes')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'solicitudes', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'solicitudes'])>
                Solicitudes
                @if ($solicitudesPendientes->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $solicitudesPendientes->count() }}</span>
                @endif
            </button>
            <button wire:click="$set('tab', 'emitir')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'emitir', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'emitir'])>
                Emitir certificado
            </button>
        @endif
        @if ($puedeVerHistorial)
            <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
                Historial
            </button>
        @endif
        @if ($puedeGestionarPlantilla)
            <button wire:click="$set('tab', 'plantilla')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'plantilla', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'plantilla'])>
                Plantilla
            </button>
        @endif
    </div>

    {{-- Solicitudes pendientes --}}
    @if ($tab === 'solicitudes' && $puedeEmitir)
        <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
            @forelse ($solicitudesPendientes as $solicitud)
                <div class="px-4 py-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink">
                                {{ $solicitud->estudiante?->nombreCompleto() ?? '—' }}
                                <x-badge variant="neutral" class="ml-1">{{ $solicitud->tipo->label() }}</x-badge>
                            </p>
                            <p class="text-xs text-ink-faint">
                                {{ $solicitud->motivo }}
                                @if ($solicitud->matricula)
                                    · {{ $solicitud->matricula->grado->nombre }} · {{ $solicitud->matricula->ciclo->nombre }}
                                @endif
                            </p>
                            <p class="text-xs text-ink-faint">
                                Solicitado el {{ $solicitud->created_at->format('d/m/Y') }}
                                @if ($solicitud->metodo_entrega)
                                    · {{ $solicitud->metodo_entrega->label() }}
                                    @if ($solicitud->correo_entrega)
                                        ({{ $solicitud->correo_entrega }})
                                    @endif
                                @endif
                            </p>
                            @if ($solicitud->getMedia('requisitos')->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach ($solicitud->getMedia('requisitos') as $requisito)
                                        <a href="{{ $requisito->getUrl() }}" target="_blank" class="rounded-full bg-surface-2 px-2 py-0.5 text-xs text-accent hover:underline">{{ $requisito->file_name }}</a>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-xs text-ink-faint">Sin requisitos adjuntos.</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-secondary-button type="button" x-on:click="$store.confirm.preguntar('¿Emitir «{{ $solicitud->tipo->label() }}» para esta solicitud?', () => $wire.emitirDeSolicitud({{ $solicitud->id }}), { etiquetaConfirmar: 'Emitir' })">
                            Emitir {{ $solicitud->tipo->label() }}
                        </x-secondary-button>
                        <input
                            type="text"
                            wire:model="motivoRechazo.{{ $solicitud->id }}"
                            placeholder="Motivo de rechazo…"
                            class="w-52 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                        >
                        <button type="button" wire:click="rechazarSolicitud({{ $solicitud->id }})" class="text-xs font-medium text-danger hover:underline">Rechazar</button>
                        <x-input-error :messages="$errors->get('motivoRechazo.'.$solicitud->id)" class="mt-0" />
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay solicitudes pendientes.</p>
            @endforelse
        </div>
    @endif

    {{-- Emitir documento directo --}}
    @if ($tab === 'emitir' && $puedeEmitir)
        <div class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div>
                <x-input-label value="Estudiante" />
                @if ($estudianteSeleccionadoId)
                    <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                        {{ $estudianteSeleccionadoNombre }}
                        <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
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
                <x-input-error :messages="$errors->get('estudianteSeleccionadoId')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="tipoDocumentoEmitir" value="Documento" />
                <x-select-input
                    wire:model="tipoDocumentoEmitir"
                    id="tipoDocumentoEmitir"
                    class="mt-1 block w-full"
                    :options="collect($tiposDocumentoConPlantilla)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                />
                <x-input-error :messages="$errors->get('tipoDocumentoEmitir')" class="mt-1" />
            </div>

            @if ($estudianteSeleccionadoId)
                <div>
                    <x-input-label for="matriculaId" value="Matrícula (opcional)" />
                    <x-select-input
                        wire:model="matriculaId"
                        id="matriculaId"
                        class="mt-1 block w-full"
                        :options="collect($matriculasDelEstudiante)->mapWithKeys(fn ($matricula) => [$matricula->id => $matricula->grado->nombre.' · '.$matricula->ciclo->nombre])->prepend('Sin vincular a una matrícula específica', '')"
                    />
                    <x-input-error :messages="$errors->get('matriculaId')" class="mt-1" />
                </div>
            @endif

            <div>
                <x-input-label for="observaciones" value="Observaciones (opcional)" />
                <textarea wire:model="observaciones" id="observaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <x-input-error :messages="$errors->get('observaciones')" class="mt-1" />
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="emitir">Emitir documento</x-primary-button>
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial' && $puedeVerHistorial)
        <div class="space-y-6">
            @if ($marcandoEntregaId)
                <div class="rounded-lg border border-accent/30 bg-accent-soft p-4">
                    <form wire:submit="confirmarEntrega" class="flex flex-wrap items-end gap-3">
                        <div>
                            <x-input-label value="Foto de la entrega (opcional, si fue física)" />
                            <input type="file" wire:model="fotoEntrega" accept="image/*" class="mt-1 block text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-sm file:text-ink">
                            <x-input-error :messages="$errors->get('fotoEntrega')" class="mt-1" />
                        </div>
                        <x-primary-button type="submit">Confirmar entrega</x-primary-button>
                        <x-secondary-button type="button" wire:click="cancelarEntrega">Cancelar</x-secondary-button>
                    </form>
                </div>
            @endif

            <div>
                <h2 class="mb-2 font-display text-sm text-ink">Certificados</h2>
                <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                    @forelse ($historial as $certificado)
                        <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 text-sm">
                            <div>
                                <p class="text-ink">
                                    {{ $certificado->estudiante?->nombreCompleto() ?? '—' }}
                                    <x-badge variant="neutral" class="ml-1">{{ $certificado->tipo->label() }}</x-badge>
                                    @if ($certificado->es_duplicado)
                                        <x-badge variant="warn" class="ml-1">Duplicado</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-ink-faint">
                                    N.° {{ $certificado->numero }} · código {{ $certificado->codigo_verificacion }}
                                    @if ($certificado->matricula)
                                        · {{ $certificado->matricula->grado->nombre }}
                                    @endif
                                    · {{ $certificado->fecha_emision->format('d/m/Y') }}
                                </p>
                                @if ($certificado->entregado_en)
                                    <p class="mt-1 text-xs text-ok">Entregado el {{ $certificado->entregado_en->format('d/m/Y') }} por {{ $certificado->entregadoPor?->name }}</p>
                                @elseif ($certificado->metodo_entrega?->value === 'virtual')
                                    <p class="mt-1 text-xs text-warn">Pendiente de envío a {{ $certificado->correo_entrega }}</p>
                                @else
                                    <p class="mt-1 text-xs text-warn">Pendiente de recojo</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($certificado->getFirstMedia('pdf'))
                                    <a href="{{ $certificado->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                                @endif
                                @if ($certificado->getFirstMedia('foto_entrega'))
                                    <a href="{{ $certificado->getFirstMediaUrl('foto_entrega') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver foto</a>
                                @endif
                                @if ($puedeEmitir && ! $certificado->entregado_en)
                                    <button type="button" wire:click="iniciarEntrega('certificado', {{ $certificado->id }})" class="text-xs font-medium text-ok hover:underline">
                                        Marcar entregado
                                    </button>
                                @endif
                                @if ($puedeDuplicar)
                                    <button type="button" x-on:click="$store.confirm.preguntar('¿Emitir un duplicado de este certificado?', () => $wire.duplicar({{ $certificado->id }}), { etiquetaConfirmar: 'Duplicar' })" class="text-xs font-medium text-ink-dim hover:underline">
                                        Duplicar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay certificados emitidos.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <h2 class="mb-2 font-display text-sm text-ink">Libretas de notas</h2>
                <div class="divide-y divide-border rounded-2xl border border-border bg-surface shadow-sm">
                    @forelse ($historialLibretas as $libreta)
                        <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 text-sm">
                            <div>
                                <p class="text-ink">{{ $libreta->estudiante?->nombreCompleto() ?? '—' }}</p>
                                <p class="text-xs text-ink-faint">{{ $libreta->ciclo->nombre }} · generada el {{ $libreta->generado_en->format('d/m/Y') }}</p>
                                @if ($libreta->entregado_en)
                                    <p class="mt-1 text-xs text-ok">Entregada el {{ $libreta->entregado_en->format('d/m/Y') }} por {{ $libreta->entregadoPor?->name }}</p>
                                @elseif ($libreta->metodo_entrega?->value === 'virtual')
                                    <p class="mt-1 text-xs text-warn">Pendiente de envío a {{ $libreta->correo_entrega }}</p>
                                @elseif ($libreta->metodo_entrega)
                                    <p class="mt-1 text-xs text-warn">Pendiente de recojo</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($libreta->getFirstMedia('pdf'))
                                    <a href="{{ $libreta->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                                @endif
                                @if ($libreta->getFirstMedia('foto_entrega'))
                                    <a href="{{ $libreta->getFirstMediaUrl('foto_entrega') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver foto</a>
                                @endif
                                @if ($puedeEmitir && $libreta->metodo_entrega && ! $libreta->entregado_en)
                                    <button type="button" wire:click="iniciarEntrega('libreta', {{ $libreta->id }})" class="text-xs font-medium text-ok hover:underline">
                                        Marcar entregada
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay libretas generadas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- Plantilla del documento --}}
    @if ($tab === 'plantilla' && $puedeGestionarPlantilla)
        <div class="max-w-2xl space-y-4 rounded-2xl border border-border bg-surface shadow-sm p-6">
            <div>
                <x-input-label for="plantillaTipoSelector" value="Documento" />
                <x-select-input
                    wire:model.live="plantillaTipo"
                    id="plantillaTipoSelector"
                    class="mt-1 block w-full sm:max-w-xs"
                    :options="collect($tiposDocumentoConPlantilla)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="plantillaInstitucion" value="Institución" />
                    <x-text-input wire:model="plantillaInstitucion" id="plantillaInstitucion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('plantillaInstitucion')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="plantillaTitulo" value="Título" />
                    <x-text-input wire:model="plantillaTitulo" id="plantillaTitulo" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('plantillaTitulo')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="plantillaCuerpo" value="Cuerpo del documento" />
                <textarea wire:model="plantillaCuerpo" id="plantillaCuerpo" rows="5" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <p class="mt-1 text-xs text-ink-faint">
                    Puedes usar: <code class="rounded bg-surface-2 px-1">@{{estudiante}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{dni}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{detalle_matricula}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{grado}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{periodo}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{numero}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{fecha_emision}}</code>
                    <code class="rounded bg-surface-2 px-1">@{{codigo_verificacion}}</code>
                </p>
                <x-input-error :messages="$errors->get('plantillaCuerpo')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="plantillaPieNota" value="Nota al pie (opcional)" />
                <textarea wire:model="plantillaPieNota" id="plantillaPieNota" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <x-input-error :messages="$errors->get('plantillaPieNota')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="plantillaColorAcento" value="Color del formato" />
                <input type="color" wire:model="plantillaColorAcento" id="plantillaColorAcento" class="mt-1 block h-10 w-20 rounded-md border-border bg-surface">
                <x-input-error :messages="$errors->get('plantillaColorAcento')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3 border-t border-border pt-4">
                <x-secondary-button type="button" wire:click="previsualizarPlantilla">Descargar vista previa</x-secondary-button>
                <x-primary-button type="button" wire:click="guardarPlantilla">Guardar plantilla</x-primary-button>
            </div>
        </div>
    @endif
</div>
