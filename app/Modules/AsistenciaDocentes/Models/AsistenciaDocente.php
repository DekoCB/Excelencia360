<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaDocentes\Models;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Database\Factories\AsistenciaDocenteFactory;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Asistencia laboral de un docente para un día calendario (no por sesión
 * de clase -- eso ya lo cubre App\Modules\Asistencia para estudiantes).
 * Reutiliza el mismo vocabulario de estados
 * (App\Modules\Asistencia\Enums\EstadoAsistenciaEnum) porque significa
 * exactamente lo mismo: presente/tardanza/falta/justificado.
 *
 * @property int $id
 * @property int $docente_id
 * @property string $fecha formato Y-m-d; sin cast a Carbon para que las
 *                         búsquedas por fecha (updateOrCreate, unique)
 *                         comparen el mismo string que se les pasa
 * @property EstadoAsistenciaEnum $estado
 * @property string|null $observacion
 * @property int|null $registrado_por
 * @property-read Docente $docente
 * @property-read User|null $registradoPor
 */
class AsistenciaDocente extends Model implements HasMedia
{
    /** @use HasFactory<AsistenciaDocenteFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'asistencias_docentes';

    protected $fillable = [
        'docente_id',
        'fecha',
        'estado',
        'observacion',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAsistenciaEnum::class,
        ];
    }

    protected static function newFactory(): AsistenciaDocenteFactory
    {
        return AsistenciaDocenteFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('justificante')->singleFile();
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
