<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Database\Factories\MaterialFactory;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $titulo
 * @property TipoMaterialEnum $tipo
 * @property string|null $url
 * @property int|null $seccion_id
 * @property int $orden
 * @property-read Seccion|null $seccion
 */
class Material extends Model implements HasMedia
{
    /** @use HasFactory<MaterialFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'materiales';

    protected $fillable = [
        'curso_virtual_id',
        'seccion_id',
        'tipo',
        'titulo',
        'url',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMaterialEnum::class,
        ];
    }

    protected static function newFactory(): MaterialFactory
    {
        return MaterialFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')->singleFile();
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }
}
