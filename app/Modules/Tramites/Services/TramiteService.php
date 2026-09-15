<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Services;

use App\Models\User;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Services\NotificacionService;
use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Enums\EstadoTramiteEnum;
use App\Modules\Tramites\Models\SolicitudTramite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TramiteService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * @param  list<UploadedFile>  $adjuntos
     */
    public function registrar(
        User $solicitante,
        CategoriaTramiteEnum $categoria,
        string $asunto,
        string $descripcion,
        array $adjuntos = [],
    ): SolicitudTramite {
        return DB::transaction(function () use ($solicitante, $categoria, $asunto, $descripcion, $adjuntos) {
            /** @var SolicitudTramite $tramite */
            $tramite = SolicitudTramite::query()->create([
                'solicitante_id' => $solicitante->id,
                'categoria' => $categoria,
                'asunto' => $asunto,
                'descripcion' => $descripcion,
                'estado' => EstadoTramiteEnum::REGISTRADA,
            ]);

            foreach ($adjuntos as $archivo) {
                $tramite->addMedia($archivo)->toMediaCollection('adjuntos');
            }

            return $tramite;
        });
    }

    /**
     * Un solo punto de cambio de estado para los 7 estados posibles (en vez
     * de un método por transición, como en Certificados con solo 3): con
     * más estados, una matriz de métodos separados sería más código para
     * la misma regla. No se fuerza un orden estricto de transición -- el
     * responsable puede, por ejemplo, volver de Observada a En revisión --
     * pero si el nuevo estado lo exige (ver
     * EstadoTramiteEnum::requiereResolucion()) no se puede guardar sin
     * explicar el motivo.
     */
    public function actualizarEstado(
        SolicitudTramite $tramite,
        EstadoTramiteEnum $nuevoEstado,
        User $responsable,
        ?string $resolucion,
    ): SolicitudTramite {
        if ($nuevoEstado->requiereResolucion() && ($resolucion === null || trim($resolucion) === '')) {
            throw ValidationException::withMessages([
                'resolucion' => 'Este estado necesita una resolución que explique el motivo.',
            ]);
        }

        $esEstadoFinal = in_array($nuevoEstado, [EstadoTramiteEnum::APROBADA, EstadoTramiteEnum::DENEGADA, EstadoTramiteEnum::ATENDIDA], true);

        $tramite->update([
            'estado' => $nuevoEstado,
            'responsable_id' => $responsable->id,
            'resolucion' => $resolucion,
            'atendido_en' => $esEstadoFinal ? now() : $tramite->atendido_en,
        ]);

        if ($esEstadoFinal) {
            $this->notificaciones->notificar(
                $tramite->solicitante,
                TipoNotificacionEnum::TRAMITE_ATENDIDO,
                "Tu trámite \"{$tramite->asunto}\" quedó {$nuevoEstado->label()}.",
                route('tramites.index'),
            );
        }

        return $tramite->fresh();
    }

    /**
     * @return Collection<int, SolicitudTramite>
     */
    public function misTramites(User $solicitante): Collection
    {
        return SolicitudTramite::query()
            ->where('solicitante_id', $solicitante->id)
            ->with(['responsable', 'media'])
            ->latest()
            ->get();
    }

    /**
     * Lista institucional (todos los solicitantes) -- paginada porque, a
     * diferencia de misTramites() (acotada a un usuario), esta crece sin
     * límite con el tiempo.
     */
    public function todos(?EstadoTramiteEnum $estado = null, ?CategoriaTramiteEnum $categoria = null, int $perPage = 15): LengthAwarePaginator
    {
        return SolicitudTramite::query()
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->when($categoria, fn ($query) => $query->where('categoria', $categoria))
            ->with(['solicitante', 'responsable', 'media'])
            ->latest()
            ->paginate($perPage);
    }
}
