<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\PeriodoMatriculaFactory;
use App\Modules\Academico\Enums\EstadoPeriodoMatriculaEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoMatricula extends Model
{
    /** @use HasFactory<PeriodoMatriculaFactory> */
    use Auditable, HasFactory;

    protected $table = 'periodos_matricula';

    protected $fillable = [
        'ciclo_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoPeriodoMatriculaEnum::class,
        ];
    }

    protected static function newFactory(): PeriodoMatriculaFactory
    {
        return PeriodoMatriculaFactory::new();
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }
}
