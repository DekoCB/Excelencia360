<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Database\Factories\TareaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_virtual_id
 * @property int|null $semana
 * @property string $titulo
 * @property string|null $descripcion
 * @property Carbon $fecha_limite
 * @property int $puntaje_max
 * @property-read CursoVirtual $cursoVirtual
 * @property-read Collection<int, EntregaTarea> $entregas
 */
class Tarea extends Model
{
    /** @use HasFactory<TareaFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'curso_virtual_id',
        'semana',
        'titulo',
        'descripcion',
        'fecha_limite',
        'puntaje_max',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'datetime',
        ];
    }

    protected static function newFactory(): TareaFactory
    {
        return TareaFactory::new();
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }

    /**
     * @return HasMany<EntregaTarea, $this>
     */
    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaTarea::class);
    }

    public function estaVencida(): bool
    {
        return $this->fecha_limite->isPast();
    }
}
