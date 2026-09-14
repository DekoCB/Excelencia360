<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class AulaService
{
    /**
     * @return Collection<int, Aula>
     */
    public function todas(): Collection
    {
        return Aula::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string, ciclo_id: ?int, letra: ?string}  $datos
     */
    public function crear(array $datos): Aula
    {
        return Aula::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string, ciclo_id: ?int, letra: ?string, activa: bool}  $datos
     */
    public function actualizar(Aula $aula, array $datos): Aula
    {
        $aula->update($datos);

        return $aula;
    }

    /**
     * No se puede borrar un aula que ya tiene horarios dictándose ahí --
     * borrarla dejaría esos horarios sin aula (la FK restrictOnDelete de
     * horarios.aula_id lo impediría a nivel de base de datos de todos
     * modos, pero conviene un mensaje claro en vez de un error SQL crudo).
     */
    public function eliminar(Aula $aula): void
    {
        if ($aula->horarios()->exists()) {
            throw ValidationException::withMessages([
                'aula' => "No se puede eliminar «{$aula->nombre}»: tiene horarios asignados. Desactívala si ya no se usa.",
            ]);
        }

        $aula->delete();
    }

    /**
     * Ocupación de cada aula activa de un ciclo (más las aulas sueltas,
     * sin grupo asignado), agrupada por turno (franja institucional):
     * cuántos estudiantes le corresponden a cada horario que usa esa
     * aula, frente a su capacidad máxima. Un aula sin horarios en el
     * ciclo aparece igual, con "porFranja" vacío, para que el personal
     * también vea qué aulas están libres.
     *
     * @return SupportCollection<int, array{aula: Aula, porFranja: SupportCollection<string, array{label: string, horarios: SupportCollection<int, array{horario: Horario, estudiantes: int}>, totalEstudiantes: int}>}>
     */
    public function ocupacion(int $cicloId): SupportCollection
    {
        $aulas = Aula::query()
            ->where('activa', true)
            ->where(fn ($query) => $query->where('ciclo_id', $cicloId)->orWhereNull('ciclo_id'))
            ->orderBy('letra')
            ->orderBy('nombre')
            ->get();

        $horarios = Horario::query()
            ->where('ciclo_id', $cicloId)
            ->with(['curso', 'grado', 'dias'])
            ->get();

        return $aulas->map(function (Aula $aula) use ($horarios) {
            $deEstaAula = $horarios->where('aula_id', $aula->id);

            $porFranja = collect(FranjaHorarioEnum::cases())
                ->mapWithKeys(function (FranjaHorarioEnum $franja) use ($deEstaAula) {
                    $enFranja = $deEstaAula->filter(fn (Horario $horario) => $horario->franja() === $franja)
                        ->map(fn (Horario $horario) => [
                            'horario' => $horario,
                            'estudiantes' => $this->contarEstudiantesDelHorario($horario),
                        ])
                        ->values();

                    return [$franja->value => [
                        'label' => $franja->label(),
                        'horarios' => $enFranja,
                        'totalEstudiantes' => (int) $enFranja->sum('estudiantes'),
                    ]];
                })
                ->filter(fn (array $grupo) => $grupo['horarios']->isNotEmpty());

            return ['aula' => $aula, 'porFranja' => $porFranja];
        });
    }

    private function contarEstudiantesDelHorario(Horario $horario): int
    {
        return Matricula::query()->delHorario($horario)->count();
    }
}
