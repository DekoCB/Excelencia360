<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Console\Commands;

use App\Modules\Pagos\Models\Recibo;
use App\Modules\Pagos\Services\ReciboService;
use Illuminate\Console\Command;

/**
 * Migra los recibos existentes al formato vigente: reasigna, dentro de
 * cada serie por separado, un correlativo continuo en orden de creación,
 * y regenera el PDF de cada uno -- las dos versiones, A4 y 80mm (esta
 * última no existía antes de que se agregara el formato térmico, así que
 * también es como los recibos antiguos la obtienen retroactivamente).
 */
class RegenerarRecibos extends Command
{
    protected $signature = 'recibos:regenerar';

    protected $description = 'Reasigna el correlativo (por serie) de cada recibo existente y regenera su PDF (A4 y 80mm)';

    public function handle(ReciboService $reciboService): int
    {
        $recibos = Recibo::query()
            ->with(['pago.estudiante', 'pago.concepto', 'pago.partes', 'pago.cuota.planPago.matricula.ciclo'])
            ->orderBy('id')
            ->get();

        // Primero se pasan todos a un valor temporal único: si se reasignara
        // el correlativo definitivo fila por fila, una fila podría chocar
        // contra el numero_recibo (todavía sin actualizar) de otra de la
        // misma serie -- el unique() de (serie, numero_recibo) lo
        // rechazaría a mitad de camino.
        foreach ($recibos as $recibo) {
            $recibo->update(['numero_recibo' => "tmp-{$recibo->id}"]);
        }

        $correlativoPorSerie = [];

        foreach ($recibos as $recibo) {
            $serie = $recibo->serie->value;
            $correlativoPorSerie[$serie] = ($correlativoPorSerie[$serie] ?? 0) + 1;
            $numero = sprintf('%06d', $correlativoPorSerie[$serie]);

            $recibo->update(['numero_recibo' => $numero]);

            $reciboService->generarPdfs($recibo, $recibo->pago, $recibo->serie);
        }

        $this->info("Recibos regenerados: {$recibos->count()}");

        return self::SUCCESS;
    }
}
