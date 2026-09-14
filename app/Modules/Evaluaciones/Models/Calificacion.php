<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Models\User;
use App\Modules\Evaluaciones\Database\Factories\CalificacionFactory;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $evaluacion_id
 * @property int $estudiante_id
 * @property float $nota_numerica
 * @property string|null $observaciones
 * @property-read Evaluacion $evaluacion
 * @property-read Estudiante|null $estudiante
 */
class Calificacion extends Model
{
    /** @use HasFactory<CalificacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'calificaciones';

    protected $fillable = [
        'evaluacion_id',
        'estudiante_id',
        'nota_numerica',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'nota_numerica' => 'decimal:2',
        ];
    }

    protected static function newFactory(): CalificacionFactory
    {
        return CalificacionFactory::new();
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function notaLetra(): NotaLetraEnum
    {
        return NotaLetraEnum::desde((float) $this->nota_numerica);
    }
}
