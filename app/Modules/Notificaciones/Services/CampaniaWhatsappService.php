<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\CampaniaWhatsapp;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use App\Modules\Pagos\Models\BloqueoAcceso;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaniaWhatsappService
{
    /**
     * @param  array{grado_id?: int, ciclo_id?: int, solo_con_deuda?: bool}  $segmento
     * @return Collection<int, Estudiante>
     */
    public function resolverDestinatarios(array $segmento): Collection
    {
        $query = Estudiante::query()
            ->whereHas('matriculas', function ($sub) use ($segmento) {
                $sub->where('estado', 'aprobada');

                if (! empty($segmento['grado_id'])) {
                    $sub->where('grado_id', $segmento['grado_id']);
                }

                if (! empty($segmento['ciclo_id'])) {
                    $sub->where('ciclo_id', $segmento['ciclo_id']);
                }
            });

        if (! empty($segmento['solo_con_deuda'])) {
            $query->whereIn('id', BloqueoAcceso::query()->where('activo', true)->pluck('estudiante_id'));
        }

        return $query->with('apoderado')->get();
    }

    /**
     * @param  array{grado_id?: int, ciclo_id?: int, solo_con_deuda?: bool}  $segmento
     */
    public function crearYEnviar(string $nombre, PlantillaWhatsapp $plantilla, array $segmento, User $creador): CampaniaWhatsapp
    {
        $destinatarios = $this->resolverDestinatarios($segmento);

        if ($destinatarios->isEmpty()) {
            throw ValidationException::withMessages([
                'segmento' => 'El segmento seleccionado no tiene destinatarios con un teléfono de contacto.',
            ]);
        }

        return DB::transaction(function () use ($nombre, $plantilla, $segmento, $creador, $destinatarios) {
            /** @var CampaniaWhatsapp $campania */
            $campania = CampaniaWhatsapp::query()->create([
                'nombre' => $nombre,
                'plantilla_id' => $plantilla->id,
                'segmento' => $segmento,
                'estado' => EstadoCampaniaEnum::ENVIANDO,
                'creado_por' => $creador->id,
            ]);

            $creados = 0;

            foreach ($destinatarios as $estudiante) {
                $telefono = $estudiante->telefonoDeContacto();

                if (! $telefono) {
                    continue;
                }

                /** @var MensajeWhatsapp $mensaje */
                $mensaje = MensajeWhatsapp::query()->create([
                    'campania_id' => $campania->id,
                    'estudiante_id' => $estudiante->id,
                    'telefono' => $telefono,
                    'contenido' => $plantilla->renderizar(['nombre' => $estudiante->nombreCompleto()]),
                    'tipo' => TipoMensajeWhatsappEnum::CAMPANIA,
                ]);

                EnviarMensajeWhatsappJob::dispatch($mensaje);
                $creados++;
            }

            $campania->update(['total_destinatarios' => $creados]);

            return $campania;
        });
    }

    /**
     * @return Collection<int, CampaniaWhatsapp>
     */
    public function todas(): Collection
    {
        return CampaniaWhatsapp::query()->with(['plantilla', 'creador'])->latest('id')->get();
    }
}
