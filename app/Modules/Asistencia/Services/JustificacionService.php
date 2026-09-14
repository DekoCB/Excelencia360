<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Enums\EstadoSolicitudJustificacionEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Models\SolicitudJustificacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class JustificacionService
{
    /**
     * El estudiante solicita justificar una falta ya registrada. Reenviar
     * sobre la misma inasistencia actualiza la solicitud existente y la
     * vuelve a dejar "pendiente" (p. ej. tras un rechazo).
     */
    public function solicitar(Asistencia $asistencia, string $motivo, ?UploadedFile $documento): SolicitudJustificacion
    {
        $solicitud = SolicitudJustificacion::query()->updateOrCreate(
            ['asistencia_id' => $asistencia->id],
            [
                'motivo' => $motivo,
                'estado' => EstadoSolicitudJustificacionEnum::PENDIENTE,
                'revisado_por' => null,
                'revisado_en' => null,
                'motivo_rechazo' => null,
            ],
        );

        if ($documento) {
            $solicitud->addMedia($documento->getRealPath())
                ->usingFileName($documento->getClientOriginalName())
                ->toMediaCollection('documento');
        }

        return $solicitud;
    }

    /**
     * Aprobar cambia la inasistencia original a "justificado" — el efecto
     * real que el estudiante buscaba al enviar la solicitud.
     */
    public function aprobar(SolicitudJustificacion $solicitud, User $revisor): void
    {
        DB::transaction(function () use ($solicitud, $revisor) {
            $solicitud->update([
                'estado' => EstadoSolicitudJustificacionEnum::APROBADA,
                'revisado_por' => $revisor->id,
                'revisado_en' => now(),
            ]);

            $solicitud->asistencia->update([
                'estado' => EstadoAsistenciaEnum::JUSTIFICADO,
                'observacion' => $solicitud->asistencia->observacion ?: $solicitud->motivo,
            ]);
        });
    }

    public function rechazar(SolicitudJustificacion $solicitud, User $revisor, ?string $motivoRechazo): void
    {
        $solicitud->update([
            'estado' => EstadoSolicitudJustificacionEnum::RECHAZADA,
            'revisado_por' => $revisor->id,
            'revisado_en' => now(),
            'motivo_rechazo' => $motivoRechazo,
        ]);
    }
}
