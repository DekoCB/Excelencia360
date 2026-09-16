<?php

use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Services\CertificadoService;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $codigo = '';

    public bool $buscado = false;

    public ?Certificado $resultado = null;

    /**
     * Quien escanea el QR del PDF llega con ?codigo=... ya en la URL (ver
     * Certificado::urlVerificacion()): se autocompleta el campo y se
     * verifica de una vez, sin que la persona tenga que volver a
     * escribirlo ni tocar el botón. mount() no recibe la query string
     * como parámetro con nombre (a diferencia de los segmentos de ruta,
     * tipo {estudiante}) -- hay que leerla a mano del request.
     */
    public function mount(CertificadoService $service): void
    {
        $codigo = Request::query('codigo');

        if (! is_string($codigo) || trim($codigo) === '') {
            return;
        }

        $this->codigo = $codigo;
        $this->verificar($service);
    }

    public function verificar(CertificadoService $service): void
    {
        $this->validate(['codigo' => 'required|string|min:4|max:20']);

        $this->resultado = $service->verificar($this->codigo);
        $this->buscado = true;
    }
}; ?>

<div>
    <h1 class="font-display text-xl text-ink">Verificar certificado</h1>
    <p class="mt-1 text-sm text-ink-dim">Ingresa el código de verificación impreso en el certificado.</p>

    <form wire:submit="verificar" class="mt-6 flex gap-2">
        <x-text-input wire:model="codigo" class="block w-full uppercase" placeholder="Código de verificación" />
        <x-primary-button type="submit">Verificar</x-primary-button>
    </form>
    <x-input-error :messages="$errors->get('codigo')" class="mt-1" />

    @if ($buscado)
        <div class="mt-6">
            @if ($resultado)
                <div class="rounded-md border border-ok/30 bg-ok/10 p-4 text-sm text-ok">
                    <p class="font-semibold">Certificado válido</p>
                </div>
                <div class="mt-3 space-y-1 rounded-md border border-border bg-surface p-4 text-sm text-ink">
                    <p><span class="text-ink-faint">Estudiante:</span> {{ $resultado->estudiante?->nombreCompleto() ?? '—' }}</p>
                    <p><span class="text-ink-faint">N.° de certificado:</span> {{ $resultado->numero }}</p>
                    @if ($resultado->matricula)
                        <p><span class="text-ink-faint">Semestre:</span> {{ $resultado->matricula->grado->nombre }}</p>
                        <p><span class="text-ink-faint">Ciclo:</span> {{ $resultado->matricula->ciclo->nombre }}</p>
                    @endif
                    <p><span class="text-ink-faint">Fecha de emisión:</span> {{ $resultado->fecha_emision->format('d/m/Y') }}</p>
                </div>
            @else
                <div class="rounded-md border border-danger/30 bg-danger/10 p-4 text-sm text-danger">
                    No se encontró ningún certificado con ese código. Verifica que esté escrito correctamente.
                </div>
            @endif
        </div>
    @endif
</div>
