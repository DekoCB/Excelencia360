<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Evaluaciones\Database\Factories\PreguntaFactory;
use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una pregunta de una Evaluacion de tipo Virtual. Opción múltiple/única
 * traen su banco de Alternativa; pregunta abierta no.
 *
 * @property int $id
 * @property int $evaluacion_id
 * @property TipoPreguntaEnum $tipo
 * @property string $enunciado
 * @property float $puntaje
 * @property int $orden
 * @property-read Evaluacion $evaluacion
 */
class Pregunta extends Model
{
    /** @use HasFactory<PreguntaFactory> */
    use Auditable, HasFactory;

    protected $table = 'preguntas';

    protected $fillable = [
        'evaluacion_id',
        'tipo',
        'enunciado',
        'puntaje',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPreguntaEnum::class,
            'puntaje' => 'decimal:2',
        ];
    }

    protected static function newFactory(): PreguntaFactory
    {
        return PreguntaFactory::new();
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    /**
     * @return HasMany<Alternativa, $this>
     */
    public function alternativas(): HasMany
    {
        return $this->hasMany(Alternativa::class)->orderBy('orden');
    }

    /**
     * @return HasMany<RespuestaEstudiante, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }
}
