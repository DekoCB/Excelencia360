<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Jobs;

use App\Modules\Matricula\Models\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerarConstanciaVacantePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Matricula $matricula,
    ) {}

    public function handle(): void
    {
        $pdf = Pdf::loadView('pdf.constancia-vacante', ['matricula' => $this->matricula]);

        $this->matricula
            ->addMediaFromString($pdf->output())
            ->usingFileName("constancia-vacante-{$this->matricula->id}.pdf")
            ->toMediaCollection('constancia');
    }
}
