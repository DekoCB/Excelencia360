<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Models;

use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Database\Factories\AsistenciaFactory;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $horario_id
 * @property int $estudiante_id
 * @property string $fecha formato Y-m-d; sin cast a Carbon para que las
 *                         búsquedas por fecha (updateOrCreate, unique)
 *                         comparen el mismo string que se les pasa
 * @property EstadoAsistenciaEnum $estado
 * @property string|null $observacion
 * @property-read Horario $horario
 * @property-read Estudiante|null $estudiante
 * @property-read SolicitudJustificacion|null $solicitudJustificacion
 */
class Asistencia extends Model implements HasMedia
{
    /** @use HasFactory<AsistenciaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'horario_id',
        'estudiante_id',
        'fecha',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAsistenciaEnum::class,
        ];
    }

    protected static function newFactory(): AsistenciaFactory
    {
        return AsistenciaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('justificante')->singleFile();
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function solicitudJustificacion(): HasOne
    {
        return $this->hasOne(SolicitudJustificacion::class);
    }
}
