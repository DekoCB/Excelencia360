<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Models;

use App\Modules\Biblioteca\Database\Factories\EjemplarFactory;
use App\Modules\Biblioteca\Enums\EstadoEjemplarEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $libro_id
 * @property string $codigo_inventario
 * @property EstadoEjemplarEnum $estado
 * @property-read Libro $libro
 * @property-read Collection<int, Prestamo> $prestamos
 */
class Ejemplar extends Model
{
    /** @use HasFactory<EjemplarFactory> */
    use Auditable, HasFactory;

    protected $table = 'ejemplares';

    protected $fillable = [
        'libro_id',
        'codigo_inventario',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoEjemplarEnum::class,
        ];
    }

    protected static function newFactory(): EjemplarFactory
    {
        return EjemplarFactory::new();
    }

    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }

    /**
     * @return HasMany<Prestamo, $this>
     */
    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }
}
