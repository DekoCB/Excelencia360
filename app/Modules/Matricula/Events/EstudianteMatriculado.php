<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Events;

use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EstudianteMatriculado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Matricula $matricula,
    ) {}
}
