<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Database\Factories\ClaseGrabadaFactory;
use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $titulo
 * @property TipoClaseGrabadaEnum $tipo
 * @property string|null $url
 * @property int|null $semana
 * @property int $orden
 */
class ClaseGrabada extends Model implements HasMedia
{
    /** @use HasFactory<ClaseGrabadaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'clases_grabadas';

    protected $fillable = [
        'curso_virtual_id',
        'semana',
        'tipo',
        'titulo',
        'url',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoClaseGrabadaEnum::class,
        ];
    }

    protected static function newFactory(): ClaseGrabadaFactory
    {
        return ClaseGrabadaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('video')->singleFile();
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }
}
