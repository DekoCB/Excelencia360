<?php

use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\MetodoEntregaEnum;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tipoDocumento = '';

    public string $matriculaId = '';

    public string $motivo = '';

    public string $metodoEntrega = '';

    public string $correoEntrega = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $requisitos = [];

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('certificados.solicitar') && $user->estudiante, 403);

        $this->tipoDocumento = TipoDocumentoEnum::CONSTANCIA_ESTUDIOS->value;
    }

    public function solicitar(CertificadoService $service, BloqueoAccesoService $bloqueos): void
    {
        $estudiante = Auth::user()->estudiante;
        abort_unless($estudiante !== null, 403);
        abort_if($bloqueos->tieneCuotasVencidasEnCicloActual($estudiante), 403);

        $this->validate([
            'tipoDocumento' => 'required|string|in:'.implode(',', array_column(TipoDocumentoEnum::constancias(), 'value')),
            'matriculaId' => 'nullable|integer|exists:matriculas,id',
            'motivo' => 'required|string|max:500',
            'requisitos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'metodoEntrega' => 'required|string|in:'.implode(',', array_column(MetodoEntregaEnum::cases(), 'value')),
            'correoEntrega' => MetodoEntregaEnum::tryFrom($this->metodoEntrega)?->requiereCorreo()
                ? 'required|email|max:150'
                : 'nullable|email|max:150',
        ]);

        $matricula = $this->matriculaId !== '' ? Matricula::query()->findOrFail($this->matriculaId) : null;

        $service->solicitar(
            $estudiante,
            $matricula,
            $this->motivo,
            $this->requisitos,
            TipoDocumentoEnum::from($this->tipoDocumento),
            MetodoEntregaEnum::from($this->metodoEntrega),
            $this->correoEntrega ?: null,
        );

        $this->reset(['matriculaId', 'motivo', 'requisitos', 'metodoEntrega', 'correoEntrega']);
        $this->tipoDocumento = TipoDocumentoEnum::CONSTANCIA_ESTUDIOS->value;
        session()->flash('status', 'Solicitud enviada. Te avisaremos cuando tu documento esté listo.');
    }

    public function with(CertificadoService $certificados, BloqueoAccesoService $bloqueos): array
    {
        $estudiante = Auth::user()->estudiante;

        // Este módulo es solo para constancias (conducta, estudios,
        // vacante): el certificado de estudios y la libreta se solicitan
        // desde su propia pantalla (ver certificados/mis-certificados.blade.php).
        $tiposDelModulo = TipoDocumentoEnum::constancias();
        $enEsteModulo = fn ($documento) => in_array($documento->tipo, $tiposDelModulo, true);

        return [
            'tiposDocumento' => $tiposDelModulo,
            'misConstancias' => $certificados->misCertificados($estudiante)->filter($enEsteModulo)->values(),
            'misSolicitudes' => $certificados->misSolicitudes($estudiante)->filter($enEsteModulo)->values(),
            'matriculas' => Matricula::query()
                ->where('estudiante_id', $estudiante->id)
                ->with(['grado', 'ciclo'])
                ->latest('fecha_matricula')
                ->get(),
            'tieneDeudaCicloActual' => $bloqueos->tieneCuotasVencidasEnCicloActual($estudiante),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis constancias</h1>
        <p class="mt-1 text-sm text-ink-dim">Solicita una constancia de estudios, de vacante o de buena conducta, y revisa las que ya se te emitieron.</p>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Solicitar una constancia</h2>

        @if ($tieneDeudaCicloActual)
            <div class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-4 text-sm text-danger">
                <p class="font-medium">No puedes solicitar documentos por ahora.</p>
                <p class="mt-1">Tienes cuotas vencidas del ciclo actual. Regulariza tu deuda en
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                    o comunícate con Cobranza para un compromiso de pago.</p>
            </div>
        @else
            <form wire:submit="solicitar" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="tipoDocumento" value="Constancia" />
                    <x-select-input
                        wire:model="tipoDocumento"
                        id="tipoDocumento"
                        class="mt-1 block w-full"
                        :options="collect($tiposDocumento)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                    />
                    <x-input-error :messages="$errors->get('tipoDocumento')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="matriculaId" value="Matrícula (opcional)" />
                    <x-select-input
                        wire:model="matriculaId"
                        id="matriculaId"
                        class="mt-1 block w-full"
                        :options="collect($matriculas)->mapWithKeys(fn ($matricula) => [$matricula->id => $matricula->grado->nombre.' · '.$matricula->ciclo->nombre])->prepend('Sin vincular a una matrícula específica', '')"
                    />
                    <x-input-error :messages="$errors->get('matriculaId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="motivo" value="Motivo de la solicitud" />
                    <textarea wire:model="motivo" id="motivo" rows="2" placeholder="Ej. trámite laboral, continuación de estudios…" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('motivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="requisitos" value="Requisitos (opcional)" />
                    <input type="file" wire:model="requisitos" id="requisitos" multiple class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:text-ink" accept=".pdf,.jpg,.jpeg,.png">
                    <p class="mt-1 text-xs text-ink-faint">Adjunta copia de tu DNI u otros documentos que pida administración (PDF o imagen, máx. 4 MB cada uno).</p>
                    <x-input-error :messages="$errors->get('requisitos')" class="mt-1" />
                    <x-input-error :messages="$errors->get('requisitos.*')" class="mt-1" />
                </div>

                <x-documentos.eleccion-entrega :metodo-actual="$metodoEntrega" />

                <div class="flex justify-end">
                    <x-primary-button type="submit">Solicitar</x-primary-button>
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Mis constancias</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misConstancias as $constancia)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">
                            {{ $constancia->tipo->label() }} N.° {{ $constancia->numero }}
                            @if ($constancia->es_duplicado)
                                <x-badge variant="warn" class="ml-1">Duplicado</x-badge>
                            @endif
                        </p>
                        <p class="text-xs text-ink-faint">
                            @if ($constancia->matricula)
                                {{ $constancia->matricula->grado->nombre }} ·
                            @endif
                            emitido el {{ $constancia->fecha_emision->format('d/m/Y') }}
                        </p>
                        @if ($constancia->entregado_en)
                            <p class="mt-1 text-xs text-ok">Entregado el {{ $constancia->entregado_en->format('d/m/Y') }}</p>
                        @elseif ($constancia->metodo_entrega?->value === 'virtual')
                            <p class="mt-1 text-xs text-warn">Pendiente de envío a {{ $constancia->correo_entrega }}</p>
                        @else
                            <p class="mt-1 text-xs text-warn">Pendiente de recojo en administración</p>
                        @endif
                    </div>
                    @if ($constancia->getFirstMedia('pdf'))
                        <a href="{{ $constancia->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no tienes constancias emitidas.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Mis solicitudes</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misSolicitudes as $solicitud)
                <div class="py-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-ink">{{ $solicitud->tipo->label() }} — {{ $solicitud->motivo }}</p>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-ok/10 text-ok' => $solicitud->estado->value === 'atendida',
                            'bg-warn/10 text-warn' => $solicitud->estado->value === 'pendiente',
                            'bg-danger/10 text-danger' => $solicitud->estado->value === 'rechazada',
                        ])>{{ $solicitud->estado->label() }}</span>
                    </div>
                    <p class="text-xs text-ink-faint">
                        Solicitado el {{ $solicitud->created_at->format('d/m/Y') }}
                        @if ($solicitud->metodo_entrega)
                            · {{ $solicitud->metodo_entrega->label() }}
                        @endif
                        @if ($solicitud->getMedia('requisitos')->isNotEmpty())
                            · {{ $solicitud->getMedia('requisitos')->count() }} requisito(s) adjunto(s)
                        @endif
                    </p>
                    @if ($solicitud->estado->value === 'rechazada' && $solicitud->motivo_rechazo)
                        <p class="text-xs text-danger">{{ $solicitud->motivo_rechazo }}</p>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">No has solicitado constancias todavía.</p>
            @endforelse
        </div>
    </div>
</div>
