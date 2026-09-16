<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Evaluaciones\Database\Factories\RespuestaEstudianteFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La respuesta de un estudiante a una Pregunta puntual. Para opción
 * múltiple/única, alternativas_elegidas trae los id de Alternativa
 * marcados; para pregunta abierta, texto_respuesta trae el texto libre.
 * puntaje_obtenido se llena solo (al enviar) para las autocalificables, y
 * a mano por el docente para las abiertas -- ver IntentoEvaluacionService.
 *
 * @property int $id
 * @property int $pregunta_id
 * @property int $estudiante_id
 * @property list<int>|null $alternativas_elegidas
 * @property string|null $texto_respuesta
 * @property float|null $puntaje_obtenido
 * @property-read Pregunta $pregunta
 * @property-read Estudiante $estudiante
 */
class RespuestaEstudiante extends Model
{
    /** @use HasFactory<RespuestaEstudianteFactory> */
    use Auditable, HasFactory;

    protected $table = 'respuestas_estudiante';

    protected $fillable = [
        'pregunta_id',
        'estudiante_id',
        'alternativas_elegidas',
        'texto_respuesta',
        'puntaje_obtenido',
    ];

    protected function casts(): array
    {
        return [
            'alternativas_elegidas' => 'array',
            'puntaje_obtenido' => 'decimal:2',
        ];
    }

    protected static function newFactory(): RespuestaEstudianteFactory
    {
        return RespuestaEstudianteFactory::new();
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
