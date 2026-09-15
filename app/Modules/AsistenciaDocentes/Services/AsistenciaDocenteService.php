<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaDocentes\Services;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Models\AsistenciaDocente;
use App\Modules\Docentes\Models\Docente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Control de asistencia laboral de docentes, un registro por día (no por
 * sesión de clase -- eso ya lo resuelve App\Modules\Asistencia). Solo
 * cubre a Docente: Personal (portería, limpieza, psicología...) no tiene
 * cuenta ni horario asignado en el modelo actual, así que no hay a quién
 * atribuirle un registro (ver auditoría de este módulo).
 */
class AsistenciaDocenteService
{
    /**
     * @return Collection<int, Docente>
     */
    public function docentesActivos(): Collection
    {
        return Docente::query()
            ->with('usuario')
            ->get()
            ->sortBy(fn (Docente $docente) => $docente->usuario->name)
            ->values();
    }

    /**
     * @return Collection<int, AsistenciaDocente>
     */
    public function deDia(string $fecha): Collection
    {
        return AsistenciaDocente::query()
            ->where('fecha', $fecha)
            ->get()
            ->keyBy('docente_id');
    }

    /**
     * @param  array<int, string>  $registros  docente_id => EstadoAsistenciaEnum::value
     * @param  array<int, string|null>  $observaciones  docente_id => motivo
     * @param  array<int, UploadedFile|null>  $justificantes  docente_id => documento de sustento
     */
    public function registrar(User $registradoPor, string $fecha, array $registros, array $observaciones = [], array $justificantes = []): void
    {
        DB::transaction(function () use ($registradoPor, $fecha, $registros, $observaciones, $justificantes) {
            foreach ($registros as $docenteId => $estado) {
                $asistencia = AsistenciaDocente::query()->updateOrCreate(
                    ['docente_id' => $docenteId, 'fecha' => $fecha],
                    ['estado' => $estado, 'observacion' => $observaciones[$docenteId] ?? null, 'registrado_por' => $registradoPor->id],
                );

                if (! empty($justificantes[$docenteId])) {
                    $asistencia->addMedia($justificantes[$docenteId]->getRealPath())
                        ->usingFileName($justificantes[$docenteId]->getClientOriginalName())
                        ->toMediaCollection('justificante');
                }
            }
        });
    }

    /**
     * @return Collection<int, AsistenciaDocente>
     */
    public function historialDocente(Docente $docente, int $meses = 3): Collection
    {
        return AsistenciaDocente::query()
            ->where('docente_id', $docente->id)
            ->where('fecha', '>=', now()->subMonths($meses)->format('Y-m-d'))
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return array{total: int, asistio: int, porcentaje: float, por_estado: array<string, int>}
     */
    public function resumenDocente(Docente $docente, int $meses = 3): array
    {
        $registros = $this->historialDocente($docente, $meses);

        $total = $registros->count();
        $asistio = $registros->filter(fn (AsistenciaDocente $asistencia) => $asistencia->estado->cuentaComoAsistio())->count();

        $porEstado = [];
        foreach (EstadoAsistenciaEnum::cases() as $estado) {
            $porEstado[$estado->value] = $registros->where('estado', $estado)->count();
        }

        return [
            'total' => $total,
            'asistio' => $asistio,
            'porcentaje' => $total > 0 ? round(($asistio / $total) * 100, 1) : 0.0,
            'por_estado' => $porEstado,
        ];
    }
}
