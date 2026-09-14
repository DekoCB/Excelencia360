<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\BloqueoAcceso;
use App\Modules\Pagos\Models\Cuota;
use Illuminate\Database\Eloquent\Collection;

class BloqueoAccesoService
{
    /**
     * Umbral institucional: dos o más cuotas vencidas sin pagar activan el
     * bloqueo de acceso a notas.
     */
    private const CUOTAS_VENCIDAS_PARA_BLOQUEAR = 2;

    public function __construct(
        private readonly CicloRepositoryInterface $ciclos,
    ) {}

    /**
     * @return Collection<int, Cuota>
     */
    public function cuotasVencidasDe(Estudiante $estudiante): Collection
    {
        return Cuota::query()
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->whereHas('planPago', function ($query) use ($estudiante) {
                $query->whereHas('matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id));
            })
            ->with('planPago')
            ->get();
    }

    public function estaBloqueado(Estudiante $estudiante): bool
    {
        return BloqueoAcceso::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Regla específica (distinta del bloqueo general de acceso): un
     * estudiante con CUALQUIER cuota vencida del ciclo actualmente activo no
     * puede solicitar certificado ni acceder a sus evaluaciones. No depende
     * del umbral de {@see self::CUOTAS_VENCIDAS_PARA_BLOQUEAR} ni del
     * registro persistido en {@see BloqueoAcceso}, que sigue rigiendo el
     * bloqueo general (dashboard, mi cuenta, etc.).
     */
    public function tieneCuotasVencidasEnCicloActual(Estudiante $estudiante): bool
    {
        $cicloActual = $this->ciclos->activo();

        if ($cicloActual === null) {
            return false;
        }

        return Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->whereHas('planPago.matricula', function ($query) use ($estudiante, $cicloActual) {
                $query->where('estudiante_id', $estudiante->id)
                    ->where('ciclo_id', $cicloActual->id);
            })
            ->exists();
    }

    public function bloqueoActivoDe(Estudiante $estudiante): ?BloqueoAcceso
    {
        return BloqueoAcceso::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('activo', true)
            ->latest('fecha_bloqueo')
            ->first();
    }

    /**
     * Recalcula si el estudiante debe estar bloqueado según sus cuotas
     * vencidas y crea o levanta el bloqueo según corresponda. Se llama tras
     * cada aprobación/rechazo de pago y tras crear un plan de pago.
     */
    public function evaluarYDesbloquear(Estudiante $estudiante): void
    {
        $cuotasVencidas = $this->cuotasVencidasDe($estudiante)->count();
        $bloqueoActivo = $this->bloqueoActivoDe($estudiante);

        if ($cuotasVencidas >= self::CUOTAS_VENCIDAS_PARA_BLOQUEAR) {
            if (! $bloqueoActivo) {
                BloqueoAcceso::query()->create([
                    'estudiante_id' => $estudiante->id,
                    'motivo' => "{$cuotasVencidas} cuotas vencidas sin pagar",
                    'fecha_bloqueo' => now(),
                    'activo' => true,
                ]);
            }

            return;
        }

        if ($bloqueoActivo) {
            $bloqueoActivo->update([
                'fecha_desbloqueo' => now(),
                'activo' => false,
            ]);
        }
    }
}
