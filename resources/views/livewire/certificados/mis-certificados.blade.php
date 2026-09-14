<?php

use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Evaluaciones\Services\LibretaService;
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

        $this->tipoDocumento = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value;
    }

    public function solicitar(CertificadoService $service, BloqueoAccesoService $bloqueos): void
    {
        $estudiante = Auth::user()->estudiante;
        abort_unless($estudiante !== null, 403);
        abort_if($bloqueos->tieneCuotasVencidasEnCicloActual($estudiante), 403);

        $esLibreta = TipoDocumentoEnum::tryFrom($this->tipoDocumento)?->esLibreta() === true;

        $this->validate([
            'tipoDocumento' => 'required|string|in:'.implode(',', array_column(TipoDocumentoEnum::certificados(), 'value')),
            'matriculaId' => $esLibreta ? 'required|integer|exists:matriculas,id' : 'nullable|integer|exists:matriculas,id',
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
        $this->tipoDocumento = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS->value;
        session()->flash('status', 'Solicitud enviada. Te avisaremos cuando tu documento esté listo.');
    }

    public function with(CertificadoService $certificados, LibretaService $libretas, BloqueoAccesoService $bloqueos): array
    {
        $estudiante = Auth::user()->estudiante;

        // Este módulo es solo para el certificado de estudios y la libreta
        // de notas: las constancias se solicitan desde su propia pantalla
        // (ver constancias/mis-constancias.blade.php).
        $tiposDelModulo = TipoDocumentoEnum::certificados();
        $enEsteModulo = fn ($documento) => in_array($documento->tipo, $tiposDelModulo, true);

        return [
            'tiposDocumento' => $tiposDelModulo,
            'misCertificados' => $certificados->misCertificados($estudiante)->filter($enEsteModulo)->values(),
            'misLibretas' => $libretas->misLibretas($estudiante),
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
        <h1 class="font-display text-2xl text-ink">Mis certificados</h1>
        <p class="mt-1 text-sm text-ink-dim">Solicita tu certificado de estudios o tu libreta de notas, y revisa los que ya se te emitieron.</p>
    </x-slot>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Solicitar un documento</h2>

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
                    <x-input-label for="tipoDocumento" value="Documento" />
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
                    <p class="mt-1 text-xs text-ink-faint">Para la libreta de notas, elige la matrícula del ciclo que quieres consultar.</p>
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
        <h2 class="text-sm font-semibold text-ink">Mis certificados</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misCertificados as $certificado)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">
                            {{ $certificado->tipo->label() }} N.° {{ $certificado->numero }}
                            @if ($certificado->es_duplicado)
                                <x-badge variant="warn" class="ml-1">Duplicado</x-badge>
                            @endif
                        </p>
                        <p class="text-xs text-ink-faint">
                            @if ($certificado->matricula)
                                {{ $certificado->matricula->grado->nombre }} ·
                            @endif
                            emitido el {{ $certificado->fecha_emision->format('d/m/Y') }}
                        </p>
                        @if ($certificado->entregado_en)
                            <p class="mt-1 text-xs text-ok">Entregado el {{ $certificado->entregado_en->format('d/m/Y') }}</p>
                        @elseif ($certificado->metodo_entrega?->value === 'virtual')
                            <p class="mt-1 text-xs text-warn">Pendiente de envío a {{ $certificado->correo_entrega }}</p>
                        @else
                            <p class="mt-1 text-xs text-warn">Pendiente de recojo en administración</p>
                        @endif
                    </div>
                    @if ($certificado->getFirstMedia('pdf'))
                        <a href="{{ $certificado->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no tienes certificados emitidos.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
        <h2 class="text-sm font-semibold text-ink">Mis libretas de notas</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misLibretas as $libreta)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $libreta->ciclo->nombre }}</p>
                        <p class="text-xs text-ink-faint">generada el {{ $libreta->generado_en->format('d/m/Y') }}</p>
                        @if ($libreta->entregado_en)
                            <p class="mt-1 text-xs text-ok">Entregada el {{ $libreta->entregado_en->format('d/m/Y') }}</p>
                        @elseif ($libreta->metodo_entrega?->value === 'virtual')
                            <p class="mt-1 text-xs text-warn">Pendiente de envío a {{ $libreta->correo_entrega }}</p>
                        @elseif ($libreta->metodo_entrega)
                            <p class="mt-1 text-xs text-warn">Pendiente de recojo en administración</p>
                        @endif
                    </div>
                    @if ($libreta->getFirstMedia('pdf'))
                        <a href="{{ $libreta->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no tienes libretas generadas.</p>
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
                <p class="py-4 text-sm text-ink-faint">No has solicitado documentos todavía.</p>
            @endforelse
        </div>
    </div>
</div>
