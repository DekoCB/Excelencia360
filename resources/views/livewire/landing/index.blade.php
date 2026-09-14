<?php

use App\Modules\Landing\Services\SolicitudContactoService;
use App\Shared\Support\Institucion;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.landing')] #[Title('EXCELENCIA 360 | Formación y Capacitación')] class extends Component
{
    public string $nombre = '';

    public string $email = '';

    public string $telefono = '';

    public string $asunto = '';

    public string $mensaje = '';

    public bool $enviado = false;

    public bool $errorEnvio = false;

    public function mount(): void
    {
        // "Más información" de un curso o servicio (y la página del curso)
        // llegan con ?asunto=... para dejar el formulario ya preparado.
        $asunto = (string) request()->query('asunto', '');

        if (in_array($asunto, Institucion::asuntosContacto(), true)) {
            $this->asunto = $asunto;
        }
    }

    public function enviarMensaje(SolicitudContactoService $service): void
    {
        $this->errorEnvio = false;

        $validado = $this->validate([
            'nombre' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'telefono' => 'required|string|max:30',
            'asunto' => ['required', 'string', 'max:150', Rule::in(Institucion::asuntosContacto())],
            'mensaje' => 'required|string|max:2000',
        ]);

        try {
            $service->registrar(
                $validado['nombre'],
                $validado['email'],
                $validado['telefono'],
                $validado['asunto'],
                $validado['mensaje'],
            );
        } catch (\Throwable $excepcion) {
            // El visitante ve un aviso y conserva lo escrito; el detalle va al log.
            report($excepcion);
            $this->errorEnvio = true;

            return;
        }

        $this->reset(['nombre', 'email', 'telefono', 'asunto', 'mensaje']);
        $this->enviado = true;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'telefono' => 'teléfono',
            'asunto' => 'asunto',
            'mensaje' => 'mensaje',
        ];
    }

    public function with(): array
    {
        return [
            'institucion' => config('institucion'),
            'asuntos' => Institucion::asuntosContacto(),
        ];
    }
}; ?>

{{--
    Los enlaces "Más información" / "Solicitar información" llevan
    data-asunto: al hacer clic dentro de esta página se elige el asunto en
    el formulario sin recargar y se baja hasta él; sin JavaScript (o desde
    la página de un curso) el href con ?asunto=... hace lo mismo vía mount().
--}}
<div
    x-data
    x-on:click="
        const enlace = $event.target.closest('a[data-asunto]');
        if (! enlace) return;
        $event.preventDefault();
        $wire.set('asunto', enlace.dataset.asunto, false);
        document.getElementById('contacto')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        history.replaceState(null, '', '#contacto');
    "
>
    <x-landing.navbar />

    <main id="contenido">
        @include('livewire.landing.partials._hero')
        @include('livewire.landing.partials._propuesta-valor')
        @include('livewire.landing.partials._conocenos')
        @include('livewire.landing.partials._mision-vision')
        @include('livewire.landing.partials._valores')
        @include('livewire.landing.partials._cursos')
        @include('livewire.landing.partials._servicios')
        @include('livewire.landing.partials._blog')
        @include('livewire.landing.partials._contacto')
    </main>

    <x-landing.footer />
</div>
