<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Models;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Incidencias\Database\Factories\IncidenciaFactory;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int|null $reportado_por
 * @property int|null $horario_id
 * @property int|null $tarea_id
 * @property int|null $evaluacion_id
 * @property TipoIncidenciaEnum $tipo
 * @property string $descripcion
 * @property Carbon $fecha
 * @property Carbon|null $notificado_apoderado_en
 * @property-read Estudiante|null $estudiante
 * @property-read User|null $reportadoPor
 * @property-read Horario|null $horario
 * @property-read Tarea|null $tarea
 * @property-read Evaluacion|null $evaluacion
 */
class Incidencia extends Model
{
    /** @use HasFactory<IncidenciaFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'estudiante_id',
        'reportado_por',
        'horario_id',
        'tarea_id',
        'evaluacion_id',
        'tipo',
        'descripcion',
        'fecha',
        'notificado_apoderado_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoIncidenciaEnum::class,
            'fecha' => 'date',
            'notificado_apoderado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): IncidenciaFactory
    {
        return IncidenciaFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }
}
