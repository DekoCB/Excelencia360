<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Siagie;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El periodo SIAGIE del MINEDU (1.er periodo, 2.° periodo, Anual): un eje
 * completamente aparte del Grupo rotativo de CEBA (ver ModalidadCicloEnum,
 * CicloService). Solo el tipo ANUAL tiene además un Ciclo real detrás (con
 * Horarios propios, igual que un Grupo) -- por eso su creación delega en
 * CicloService::crear(), reutilizando la misma validación de fechas (8
 * meses de clases) y de solape que ya existía para el Ciclo anual.
 */
class SiagieService
{
    public function __construct(
        private readonly CicloService $ciclos,
    ) {}

    /**
     * @return Collection<int, Siagie>
     */
    public function listar(): Collection
    {
        return Siagie::query()->orderByDesc('anio')->orderBy('tipo')->get();
    }

    /**
     * @param  array{tipo: TipoSiagieEnum, anio: int, fecha_inicio?: ?string, fecha_fin?: ?string, estado?: EstadoCicloEnum}  $datos
     */
    public function crear(array $datos): Siagie
    {
        $datos['estado'] ??= EstadoCicloEnum::PLANIFICADO;

        $this->validarSinDuplicado($datos['tipo'], $datos['anio']);

        if ($datos['tipo'] === TipoSiagieEnum::ANUAL) {
            return $this->crearAnual($datos);
        }

        return Siagie::query()->create([
            'tipo' => $datos['tipo'],
            'anio' => $datos['anio'],
            'fecha_inicio' => $datos['fecha_inicio'] ?? null,
            'fecha_fin' => $datos['fecha_fin'] ?? null,
            'estado' => $datos['estado'],
        ]);
    }

    /**
     * @param  array{tipo: TipoSiagieEnum, anio: int, fecha_inicio?: ?string, fecha_fin?: ?string, estado?: EstadoCicloEnum}  $datos
     */
    public function actualizar(Siagie $siagie, array $datos): Siagie
    {
        $datos['estado'] ??= $siagie->estado;

        $this->validarSinDuplicado($datos['tipo'], $datos['anio'], $siagie->id);

        if ($datos['tipo'] === TipoSiagieEnum::ANUAL) {
            return $this->actualizarAnual($siagie, $datos);
        }

        $siagie->update([
            'tipo' => $datos['tipo'],
            'anio' => $datos['anio'],
            'fecha_inicio' => $datos['fecha_inicio'] ?? null,
            'fecha_fin' => $datos['fecha_fin'] ?? null,
            'estado' => $datos['estado'],
        ]);

        return $siagie->fresh();
    }

    private function validarSinDuplicado(TipoSiagieEnum $tipo, int $anio, ?int $exceptoId = null): void
    {
        $existe = Siagie::query()
            ->where('tipo', $tipo)
            ->where('anio', $anio)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'anio' => "Ya existe un SIAGIE {$tipo->label()} para el año {$anio}.",
            ]);
        }
    }

    /**
     * @param  array{tipo: TipoSiagieEnum, anio: int, fecha_inicio?: ?string, fecha_fin?: ?string, estado: EstadoCicloEnum}  $datos
     */
    private function crearAnual(array $datos): Siagie
    {
        if (($datos['fecha_inicio'] ?? null) === null || ($datos['fecha_fin'] ?? null) === null) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'El SIAGIE Anual necesita fecha de inicio y de fin (su periodo de clases real).',
            ]);
        }

        return DB::transaction(function () use ($datos) {
            /** @var Siagie $siagie */
            $siagie = Siagie::query()->create([
                'tipo' => TipoSiagieEnum::ANUAL,
                'anio' => $datos['anio'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'estado' => $datos['estado'],
            ]);

            $ciclo = $this->ciclos->crear([
                'nombre' => "SIAGIE Anual - {$datos['anio']}",
                'modalidad' => ModalidadCicloEnum::ANUAL,
                'tipo' => null,
                'anio' => $datos['anio'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
            ]);

            $ciclo->update(['siagie_id' => $siagie->id, 'estado' => $datos['estado']]);

            return $siagie;
        });
    }

    /**
     * @param  array{tipo: TipoSiagieEnum, anio: int, fecha_inicio?: ?string, fecha_fin?: ?string, estado: EstadoCicloEnum}  $datos
     */
    private function actualizarAnual(Siagie $siagie, array $datos): Siagie
    {
        if (($datos['fecha_inicio'] ?? null) === null || ($datos['fecha_fin'] ?? null) === null) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'El SIAGIE Anual necesita fecha de inicio y de fin (su periodo de clases real).',
            ]);
        }

        return DB::transaction(function () use ($siagie, $datos) {
            $ciclo = Ciclo::query()->where('siagie_id', $siagie->id)->first();

            if ($ciclo) {
                $this->ciclos->actualizar($ciclo, [
                    'nombre' => $ciclo->nombre,
                    'modalidad' => ModalidadCicloEnum::ANUAL,
                    'tipo' => null,
                    'anio' => $datos['anio'],
                    'fecha_inicio' => $datos['fecha_inicio'],
                    'fecha_fin' => $datos['fecha_fin'],
                    'estado' => $datos['estado'],
                ]);
            }

            $siagie->update([
                'anio' => $datos['anio'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'estado' => $datos['estado'],
            ]);

            return $siagie->fresh();
        });
    }
}
