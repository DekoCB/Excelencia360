<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Pagos\Models\Cuota;

/**
 * Recordatorios automáticos de cuotas por vencer, pensado para correr a
 * diario desde el Scheduler (ver routes/console.php).
 */
class RecordatorioCuotaService
{
    public function enviarRecordatorios(int $diasAntes = 7): int
    {
        // El recordatorio se repite a diario mientras la cuota siga
        // pendiente (incluso ya vencida), hasta que el estudiante pague, así
        // que el único caso a evitar es notificar dos veces el mismo día si
        // el comando se corre más de una vez. Cuota (módulo Pagos) no conoce
        // a Notificaciones, así que ese filtro se resuelve desde este lado
        // con whereNotIn, en vez de una relación whereDoesntHave.
        $cuotasYaRecordadasHoy = MensajeWhatsapp::query()
            ->where('tipo', TipoMensajeWhatsappEnum::RECORDATORIO)
            ->whereNotNull('cuota_id')
            ->whereDate('created_at', now()->toDateString())
            ->pluck('cuota_id');

        $cuotas = Cuota::query()
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<=', now()->addDays($diasAntes)->endOfDay())
            ->whereNotIn('id', $cuotasYaRecordadasHoy)
            ->with('planPago.matricula.estudiante')
            ->get();

        $enviados = 0;

        foreach ($cuotas as $cuota) {
            $estudiante = $cuota->planPago->matricula?->estudiante;

            if (! $estudiante) {
                continue;
            }

            $telefono = $estudiante->telefonoDeContacto();

            if (! $telefono) {
                continue;
            }

            $contenido = $cuota->fecha_vencimiento->isPast()
                ? sprintf(
                    'Hola, la cuota %d de %s venció el %s por S/ %s y sigue pendiente de pago. Regulariza cuanto antes para evitar el bloqueo de la libreta de notas.',
                    $cuota->numero,
                    $estudiante->nombreCompleto(),
                    $cuota->fecha_vencimiento->format('d/m/Y'),
                    number_format((float) $cuota->monto, 2),
                )
                : sprintf(
                    'Hola, recordamos que la cuota %d de %s vence el %s por S/ %s. Evita el bloqueo de la libreta de notas regularizando a tiempo.',
                    $cuota->numero,
                    $estudiante->nombreCompleto(),
                    $cuota->fecha_vencimiento->format('d/m/Y'),
                    number_format((float) $cuota->monto, 2),
                );

            /** @var MensajeWhatsapp $mensaje */
            $mensaje = MensajeWhatsapp::query()->create([
                'estudiante_id' => $estudiante->id,
                'cuota_id' => $cuota->id,
                'telefono' => $telefono,
                'contenido' => $contenido,
                'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO,
            ]);

            EnviarMensajeWhatsappJob::dispatch($mensaje);
            $enviados++;
        }

        return $enviados;
    }
}
