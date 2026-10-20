<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Eloquent;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Modules\Academico\Repositories\Contracts\HorarioRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Horario>
 */
class EloquentHorarioRepository extends BaseRepository implements HorarioRepositoryInterface
{
    /**
     * @return Builder<Horario>
     */
    protected function query(): Builder
    {
        return Horario::query()->with(['curso', 'docente', 'aula', 'ciclo', 'grado', 'dias']);
    }

    public function enAulaQueSolapan(int $aulaId, ?int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoHorarioId = null): Collection
    {
        return $this->consultaDiasSolapados($dia, $horaInicio, $horaFin, $exceptoHorarioId)
            ->whereHas('horario', function ($query) use ($aulaId, $cicloId) {
                $query->where('aula_id', $aulaId);
                $cicloId !== null ? $query->where('ciclo_id', $cicloId) : $query->whereNull('ciclo_id');
            })
            ->get();
    }

    public function delDocenteQueSolapan(int $docenteId, ?int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoHorarioId = null): Collection
    {
        return $this->consultaDiasSolapados($dia, $horaInicio, $horaFin, $exceptoHorarioId)
            ->whereHas('horario', function ($query) use ($docenteId, $cicloId) {
                $query->where('docente_id', $docenteId);
                $cicloId !== null ? $query->where('ciclo_id', $cicloId) : $query->whereNull('ciclo_id');
            })
            ->get();
    }

    public function delCiclo(int $cicloId): Collection
    {
        return $this->query()->where('ciclo_id', $cicloId)->get();
    }

    public function deCursoCorto(): Collection
    {
        return $this->query()->whereNull('ciclo_id')->get();
    }

    /**
     * @return Builder<HorarioDia>
     */
    private function consultaDiasSolapados(DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoHorarioId): Builder
    {
        return HorarioDia::query()
            ->with('horario.curso')
            ->where('dia_semana', $dia->value)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($exceptoHorarioId, fn ($query) => $query->where('horario_id', '!=', $exceptoHorarioId));
    }
}
