<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $dni = '';

    /** @var array{curso: string, estado: string}|null */
    public ?array $resultado = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('asistencia.ver_propio') && $user->estudiante, 403);
    }

    public function confirmar(AsistenciaService $service): void
    {
        $estudiante = Auth::user()->estudiante;

        $this->validate(['dni' => 'required|string']);

        if (trim($this->dni) !== $estudiante->dni) {
            $this->addError('dni', 'El DNI no coincide con tu ficha de estudiante.');

            return;
        }

        $horario = $service->horarioEnCursoDelEstudiante($estudiante);

        if (! $horario) {
            $this->addError('dni', 'No tienes ninguna clase en curso en este momento.');

            return;
        }

        $asistencia = $service->autorregistrar($horario, $estudiante);

        $this->resultado = [
            'curso' => $horario->curso->nombre,
            'estado' => $asistencia->estado->label(),
        ];
        $this->dni = '';
    }

    public function with(AsistenciaService $service): array
    {
        return [
            'horarioEnCurso' => $service->horarioEnCursoDelEstudiante(Auth::user()->estudiante),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('asistencia.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Asistencia</a>
        <h1 class="mt-1 font-display text-2xl text-ink">Marcar mi asistencia</h1>
        <p class="mt-1 text-sm text-ink-dim">Confirma tu DNI para registrarte en la clase que tienes en este momento.</p>
    </x-slot>

    <div class="mx-auto max-w-md">
        @if ($resultado)
            <div class="rounded-lg border border-ok/30 bg-ok/10 p-6 text-center">
                <x-heroicon-o-check-circle class="mx-auto h-10 w-10 text-ok" />
                <p class="mt-3 font-display text-lg text-ink">Quedaste marcado como {{ $resultado['estado'] }}</p>
                <p class="mt-1 text-sm text-ink-dim">{{ $resultado['curso'] }}</p>
                <a href="{{ route('asistencia.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent hover:underline">Volver a Asistencia</a>
            </div>
        @elseif ($horarioEnCurso)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                <p class="text-xs uppercase tracking-wide text-ink-faint">Clase en curso</p>
                <p class="mt-1 font-display text-xl text-ink">{{ $horarioEnCurso->curso->nombre }}</p>
                @php $diaDeHoy = $horarioEnCurso->diaParaFecha(now()); @endphp
                <p class="mt-1 text-sm text-ink-dim">
                    {{ $horarioEnCurso->grado->nombre }}
                    @if ($diaDeHoy)
                        · {{ $diaDeHoy->dia_semana->label() }}
                        {{ substr($diaDeHoy->hora_inicio, 0, 5) }}–{{ substr($diaDeHoy->hora_fin, 0, 5) }}
                    @endif
                </p>

                <form wire:submit="confirmar" class="mt-6">
                    <x-input-label for="dni" value="Tu DNI" />
                    <x-text-input wire:model="dni" id="dni" inputmode="numeric" autofocus class="mt-1 block w-full text-center text-lg tracking-widest" placeholder="········" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-2" />

                    <x-primary-button type="submit" class="mt-4 w-full justify-center">Confirmar mi asistencia</x-primary-button>
                </form>
            </div>
        @else
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6 text-center">
                <x-heroicon-o-clock class="mx-auto h-10 w-10 text-ink-faint" />
                <p class="mt-3 font-display text-lg text-ink">No tienes ninguna clase en este momento</p>
                <p class="mt-1 text-sm text-ink-dim">Podrás marcar tu asistencia desde que empiece tu sesión y hasta que termine.</p>
                <a href="{{ route('asistencia.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent hover:underline">Ver mis horarios</a>
            </div>
        @endif
    </div>
</div>
