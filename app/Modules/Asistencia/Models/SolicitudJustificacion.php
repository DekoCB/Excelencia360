<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Models;

use App\Models\User;
use App\Modules\Asistencia\Database\Factories\SolicitudJustificacionFactory;
use App\Modules\Asistencia\Enums\EstadoSolicitudJustificacionEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $asistencia_id
 * @property string $motivo
 * @property EstadoSolicitudJustificacionEnum $estado
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property string|null $motivo_rechazo
 * @property-read Asistencia $asistencia
 * @property-read User|null $revisadoPor
 */
class SolicitudJustificacion extends Model implements HasMedia
{
    /** @use HasFactory<SolicitudJustificacionFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'solicitudes_justificacion';

    protected $fillable = [
        'asistencia_id',
        'motivo',
        'estado',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudJustificacionEnum::class,
            'revisado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): SolicitudJustificacionFactory
    {
        return SolicitudJustificacionFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documento')->singleFile();
    }

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
