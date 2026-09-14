<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Database\Factories\EntregaTareaFactory;
use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string|null $comentario
 * @property Carbon|null $fecha_entrega
 * @property float|null $nota
 * @property EstadoEntregaEnum $estado
 * @property-read Tarea $tarea
 * @property-read Estudiante|null $estudiante
 */
class EntregaTarea extends Model implements HasMedia
{
    /** @use HasFactory<EntregaTareaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'entregas_tarea';

    protected $fillable = [
        'tarea_id',
        'estudiante_id',
        'comentario',
        'fecha_entrega',
        'nota',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'nota' => 'decimal:2',
            'estado' => EstadoEntregaEnum::class,
        ];
    }

    protected static function newFactory(): EntregaTareaFactory
    {
        return EntregaTareaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')->singleFile();
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
