<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Console\Commands;

use App\Modules\Notificaciones\Services\RecordatorioCuotaService;
use Illuminate\Console\Command;

class EnviarRecordatoriosWhatsapp extends Command
{
    protected $signature = 'whatsapp:recordatorios {--dias=7 : Días de anticipación antes del vencimiento}';

    protected $description = 'Envía recordatorios de WhatsApp para cuotas próximas a vencer';

    public function handle(RecordatorioCuotaService $service): int
    {
        $enviados = $service->enviarRecordatorios((int) $this->option('dias'));

        $this->info("Recordatorios encolados: {$enviados}");

        return self::SUCCESS;
    }
}
