<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Evaluaciones\Database\Factories\IntentoEvaluacionFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El envío de un estudiante a una Evaluacion Virtual: existe a partir del
 * momento en que envía sus respuestas (no antes -- no hay estado
 * "borrador" del lado del intento, ver EvaluacionService::enviar()), y su
 * sola existencia es lo que impide un segundo envío (un solo intento).
 *
 * @property int $id
 * @property int $evaluacion_id
 * @property int $estudiante_id
 * @property Carbon $enviado_en
 * @property Carbon|null $calificado_en
 * @property-read Evaluacion $evaluacion
 * @property-read Estudiante $estudiante
 */
class IntentoEvaluacion extends Model
{
    /** @use HasFactory<IntentoEvaluacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'intentos_evaluacion';

    protected $fillable = [
        'evaluacion_id',
        'estudiante_id',
        'enviado_en',
        'calificado_en',
    ];

    protected function casts(): array
    {
        return [
            'enviado_en' => 'datetime',
            'calificado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): IntentoEvaluacionFactory
    {
        return IntentoEvaluacionFactory::new();
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function estaCalificado(): bool
    {
        return $this->calificado_en !== null;
    }
}
