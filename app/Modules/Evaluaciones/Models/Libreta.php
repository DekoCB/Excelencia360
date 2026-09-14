<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Evaluaciones\Database\Factories\LibretaFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\MetodoEntregaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $ciclo_id
 * @property Carbon|null $generado_en
 * @property Carbon|null $entregado_en
 * @property int|null $entregado_por
 * @property MetodoEntregaEnum|null $metodo_entrega
 * @property string|null $correo_entrega
 * @property-read Estudiante|null $estudiante
 * @property-read Ciclo $ciclo
 * @property-read User|null $entregadoPor
 */
class Libreta extends Model implements HasMedia
{
    /** @use HasFactory<LibretaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'estudiante_id',
        'ciclo_id',
        'generado_en',
        'entregado_en',
        'entregado_por',
        'metodo_entrega',
        'correo_entrega',
    ];

    protected function casts(): array
    {
        return [
            'generado_en' => 'datetime',
            'entregado_en' => 'datetime',
            'metodo_entrega' => MetodoEntregaEnum::class,
        ];
    }

    protected static function newFactory(): LibretaFactory
    {
        return LibretaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
        $this->addMediaCollection('foto_entrega')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }
}
