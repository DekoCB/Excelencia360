<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Listeners;

use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Jobs\GenerarConstanciaVacantePdfJob;
use App\Modules\Matricula\Jobs\GenerarFichaMatriculaPdfJob;

class GenerarDocumentosMatriculaListener
{
    public function handle(EstudianteMatriculado $event): void
    {
        GenerarFichaMatriculaPdfJob::dispatch($event->matricula);
        GenerarConstanciaVacantePdfJob::dispatch($event->matricula);
    }
}
