<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Modules\Academico\Repositories\Contracts\HorarioRepositoryInterface;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HorarioService
{
    public function __construct(
        private readonly HorarioRepositoryInterface $horarios,
        private readonly CursoVirtualService $cursosVirtuales,
    ) {}

    /**
     * @return Collection<int, Horario>
     */
    public function delCiclo(int $cicloId): Collection
    {
        return $this->horarios->delCiclo($cicloId);
    }

    /**
     * @return Collection<int, Horario>
     */
    public function deCursoCorto(): Collection
    {
        return $this->horarios->deCursoCorto();
    }

    /**
     * @param  array{curso_id: int, docente_id: int, aula_id: int, ciclo_id: ?int, grado_id: ?int, dias: list<array{dia_semana: DiaSemanaEnum, hora_inicio: ?string, hora_fin: ?string}>}  $datos
     */
    public function crear(array $datos): Horario
    {
        $this->validarDias($datos['dias'], $datos['aula_id'], $datos['docente_id'], $datos['ciclo_id']);

        $horario = DB::transaction(function () use ($datos) {
            $horario = $this->horarios->create([
                'curso_id' => $datos['curso_id'],
                'docente_id' => $datos['docente_id'],
                'aula_id' => $datos['aula_id'],
                'ciclo_id' => $datos['ciclo_id'],
                'grado_id' => $datos['grado_id'],
            ]);

            $horario->dias()->createMany($datos['dias']);

            return $horario;
        });

        $this->activarAulaVirtual($horario);

        return $horario->load('dias');
    }

    /**
     * Para que el docente encuentre su aula virtual ya lista, sin tener que
     * "activarla" a mano después de que se le asigna el horario. Un horario
     * de curso corto se activa por curso_id, no por horario_id -- igual que
     * CursoService::activarAulaVirtualSiCorresponde() -- porque el resto
     * del sistema (CursoVirtual::esDeCursoCorto(), CursoVirtualPolicy, los
     * filtros de listado) solo reconoce un curso corto por curso_id. Se
     * sincroniza además Curso::docente_id con el del horario: es el único
     * campo que CursoVirtual::docenteId() consulta para un curso corto, así
     * que si el docente se asignó aquí (no editando el Curso) igual queda
     * como dueño de su aula virtual.
     */
    private function activarAulaVirtual(Horario $horario): void
    {
        if (! $horario->esDeCursoCorto()) {
            $this->cursosVirtuales->activarParaHorario($horario);

            return;
        }

        if ($horario->curso->docente_id !== $horario->docente_id) {
            $horario->curso->update(['docente_id' => $horario->docente_id]);
        }

        $this->cursosVirtuales->activarParaCursoCorto($horario->curso);
    }

    /**
     * @param  array{curso_id: int, docente_id: int, aula_id: int, ciclo_id: ?int, grado_id: ?int, dias: list<array{dia_semana: DiaSemanaEnum, hora_inicio: ?string, hora_fin: ?string}>}  $datos
     */
    public function actualizar(Horario $horario, array $datos): Horario
    {
        $this->validarDias($datos['dias'], $datos['aula_id'], $datos['docente_id'], $datos['ciclo_id'], $horario->id);

        $horario = DB::transaction(function () use ($horario, $datos) {
            $this->horarios->update($horario, [
                'curso_id' => $datos['curso_id'],
                'docente_id' => $datos['docente_id'],
                'aula_id' => $datos['aula_id'],
                'ciclo_id' => $datos['ciclo_id'],
                'grado_id' => $datos['grado_id'],
            ]);

            $horario->dias()->delete();
            $horario->dias()->createMany($datos['dias']);

            return $horario->load('dias');
        });

        $this->activarAulaVirtual($horario);

        return $horario;
    }

    /**
     * @param  list<array{dia_semana: DiaSemanaEnum, hora_inicio: ?string, hora_fin: ?string}>  $dias
     */
    private function validarDias(array $dias, int $aulaId, int $docenteId, ?int $cicloId, ?int $exceptoHorarioId = null): void
    {
        if ($dias === []) {
            throw ValidationException::withMessages([
                'dias' => 'Elige al menos un día de clase.',
            ]);
        }

        foreach ($dias as $dia) {
            // Un horario de curso corto puede quedar sin horas definidas
            // (días sueltos, sin hora de clase) -- sin ambas horas no hay
            // nada que cruzar contra otro horario, así que se salta el
            // resto de la validación para ese día.
            if ($dia['hora_inicio'] === null || $dia['hora_fin'] === null) {
                continue;
            }

            if ($dia['hora_fin'] <= $dia['hora_inicio']) {
                throw ValidationException::withMessages([
                    'dias' => "La hora de fin debe ser posterior a la hora de inicio el {$dia['dia_semana']->label()}.",
                ]);
            }

            $enAula = $this->horarios->enAulaQueSolapan(
                $aulaId,
                $cicloId,
                $dia['dia_semana'],
                $dia['hora_inicio'],
                $dia['hora_fin'],
                $exceptoHorarioId,
            );

            if ($enAula->isNotEmpty()) {
                $choque = $enAula->first();
                throw ValidationException::withMessages([
                    'dias' => "El aula ya está ocupada el {$dia['dia_semana']->label()} de {$this->horaCorta($choque->hora_inicio)}–{$this->horaCorta($choque->hora_fin)} por el curso «{$choque->horario->curso->nombre}».",
                ]);
            }

            $delDocente = $this->horarios->delDocenteQueSolapan(
                $docenteId,
                $cicloId,
                $dia['dia_semana'],
                $dia['hora_inicio'],
                $dia['hora_fin'],
                $exceptoHorarioId,
            );

            if ($delDocente->isNotEmpty()) {
                $choque = $delDocente->first();
                throw ValidationException::withMessages([
                    'dias' => "El docente ya tiene una clase asignada el {$dia['dia_semana']->label()} de {$this->horaCorta($choque->hora_inicio)}–{$this->horaCorta($choque->hora_fin)}: «{$choque->horario->curso->nombre}».",
                ]);
            }
        }
    }

    private function horaCorta(string $hora): string
    {
        return substr($hora, 0, 5);
    }

    /**
     * Mueve un solo día de un horario a otro día de la semana, conservando
     * su hora -- usado por el editor de arrastrar-y-soltar. Reconstruye el
     * array "dias" completo del horario dueño y lo pasa por actualizar(),
     * así reutiliza la misma validación de traslapes sin duplicarla; si el
     * nuevo día choca con otro horario en la misma aula/docente, lanza la
     * misma ValidationException que actualizar().
     */
    public function moverDia(HorarioDia $dia, DiaSemanaEnum $nuevoDia): Horario
    {
        $horario = Horario::query()->with('dias')->findOrFail($dia->horario_id);

        $dias = $horario->dias
            ->map(fn (HorarioDia $d) => [
                'dia_semana' => $d->id === $dia->id ? $nuevoDia : $d->dia_semana,
                'hora_inicio' => $d->hora_inicio,
                'hora_fin' => $d->hora_fin,
            ])
            ->all();

        return $this->actualizar($horario, [
            'curso_id' => $horario->curso_id,
            'docente_id' => $horario->docente_id,
            'aula_id' => $horario->aula_id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
            'dias' => $dias,
        ]);
    }
}
