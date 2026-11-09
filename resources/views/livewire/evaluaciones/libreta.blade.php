<?php

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Estudiante $estudiante;

    public Ciclo $ciclo;

    public bool $estaBloqueado = false;

    public bool $puedeGenerarLibreta = false;

    /** @var array<int, string> */
    public array $notasEditadas = [];

    public function mount(Estudiante $estudiante, Ciclo $ciclo, BloqueoAccesoService $bloqueos): void
    {
        $user = Auth::user();

        $esElMismoEstudiante = $user->estudiante && $user->estudiante->id === $estudiante->id;
        $puedeVerTodo = $user->hasPermissionTo('evaluaciones.ver');

        abort_unless(
            $esElMismoEstudiante || $puedeVerTodo || $this->esDocenteDeAlgunCurso($user, $estudiante, $ciclo),
            403,
        );

        $this->estudiante = $estudiante;
        $this->ciclo = $ciclo;
        // Generar/descargar la libreta oficial sigue limitado al propio
        // estudiante y a quien tiene evaluaciones.ver (Dirección/
        // Coordinación) -- un docente que solo entra a editar la nota de
        // su curso no ve este botón.
        $this->puedeGenerarLibreta = $esElMismoEstudiante || $puedeVerTodo;

        // Solo se bloquea la vista al propio estudiante; el personal con
        // evaluaciones.ver (o el docente de alguno de sus cursos) sigue
        // pudiendo consultar/editar la libreta sin que la cobranza se lo
        // impida.
        $this->estaBloqueado = $esElMismoEstudiante
            && ($bloqueos->estaBloqueado($estudiante) || $bloqueos->tieneCuotasVencidasEnCicloActual($estudiante));
    }

    /**
     * Un docente sin "evaluaciones.ver" (que solo cubre a Dirección y
     * Coordinación) igual puede abrir la libreta de un estudiante suyo
     * para editar la nota final de su curso -- mismo criterio de
     * autorización que Gate::allows('evaluaciones.editar-nota-final', ...),
     * visto aquí para al menos un horario de la matrícula del estudiante
     * en este ciclo.
     */
    private function esDocenteDeAlgunCurso(User $user, Estudiante $estudiante, Ciclo $ciclo): bool
    {
        if (! $user->hasPermissionTo('evaluaciones.registrar')) {
            return false;
        }

        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->first();

        if (! $matricula) {
            return false;
        }

        return Horario::query()->deLaMatricula($matricula)->where('docente_id', $user->id)->exists();
    }

    public function generar(LibretaService $service): void
    {
        $user = Auth::user();
        $esElMismoEstudiante = $user->estudiante && $user->estudiante->id === $this->estudiante->id;

        abort_unless($esElMismoEstudiante || $user->hasPermissionTo('evaluaciones.ver'), 403);
        abort_if($this->estaBloqueado, 403);

        $service->generar($this->estudiante, $this->ciclo);
    }

    /**
     * Guarda las notas finales editadas a mano, una por horario -- solo
     * las que el usuario puede editar (evaluaciones.editar-nota-final) y
     * que vinieron con un valor no vacío.
     */
    public function guardarNotasFinales(LibretaService $service): void
    {
        $user = Auth::user();

        foreach ($this->notasEditadas as $horarioId => $nota) {
            if ($nota === '' || $nota === null) {
                continue;
            }

            $horario = Horario::query()->findOrFail($horarioId);

            abort_unless(Gate::allows('evaluaciones.editar-nota-final', $horario), 403);

            $service->guardarNotaFinal($this->estudiante, $horario, (float) $nota, $user);
        }

        $this->notasEditadas = [];
    }

    public function with(LibretaService $service): array
    {
        $libreta = Libreta::query()
            ->where('estudiante_id', $this->estudiante->id)
            ->where('ciclo_id', $this->ciclo->id)
            ->first();

        $cursos = $service->resumenPorCursos($this->estudiante, $this->ciclo);

        return [
            'libreta' => $libreta,
            'urlPdf' => $libreta?->getFirstMediaUrl('pdf'),
            'cursos' => $cursos,
            'situacionFinal' => $cursos->isNotEmpty() ? $service->calcularSituacionFinal($cursos) : null,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Libreta de notas</h1>
        <p class="mt-1 text-sm text-ink-dim">{{ $estudiante->nombreCompleto() }} · {{ $ciclo->nombre }}</p>
    </x-slot>

    @if ($estaBloqueado)
        <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-6 text-sm text-danger">
            <p class="font-medium">Tu libreta no está disponible.</p>
            <p class="mt-1">Tienes cuotas vencidas sin pagar. Regulariza tu deuda en
                <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                o comunícate con Cobranza para un compromiso de pago.</p>
        </div>
    @else
        @if ($puedeGenerarLibreta)
            <div class="rounded-2xl border border-border bg-surface shadow-sm p-6">
                @if ($libreta && $libreta->generado_en)
                    <p class="text-sm text-ink-dim">
                        Última libreta generada el {{ $libreta->generado_en->format('d/m/Y H:i') }}.
                    </p>

                    @if ($urlPdf)
                        <a href="{{ $urlPdf }}" target="_blank" class="mt-3 inline-block text-sm font-medium text-accent hover:underline">
                            Descargar PDF →
                        </a>
                    @endif
                @else
                    <p class="text-sm text-ink-faint">Todavía no se ha generado una libreta para este ciclo.</p>
                @endif

                <div class="mt-4">
                    <x-primary-button type="button" wire:click="generar" wire:loading.attr="disabled">
                        {{ $libreta ? 'Actualizar libreta' : 'Generar libreta' }}
                    </x-primary-button>
                </div>
            </div>
        @endif

        <div class="mt-6">
            <x-evaluaciones.resumen-libreta :cursos="$cursos" :situacion-final="$situacionFinal" :puede-editar="true" />
        </div>
    @endif
</div>
