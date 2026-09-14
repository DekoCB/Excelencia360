<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\Identidad\Support\Auditable;
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
class PlantillaClaseGrabada extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    protected $table = 'plantilla_clases_grabadas';

    protected $fillable = [
        'plantilla_id',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('video')->singleFile();
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCursoVirtual::class, 'plantilla_id');
    }
}
