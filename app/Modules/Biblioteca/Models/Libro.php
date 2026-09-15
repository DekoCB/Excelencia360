<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Models;

use App\Modules\Biblioteca\Database\Factories\LibroFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $titulo
 * @property string $autor
 * @property string|null $isbn
 * @property string|null $categoria
 * @property string|null $editorial
 * @property int|null $anio_publicacion
 * @property-read Collection<int, Ejemplar> $ejemplares
 */
class Libro extends Model
{
    /** @use HasFactory<LibroFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'titulo',
        'autor',
        'isbn',
        'categoria',
        'editorial',
        'anio_publicacion',
    ];

    protected static function newFactory(): LibroFactory
    {
        return LibroFactory::new();
    }

    /**
     * @return HasMany<Ejemplar, $this>
     */
    public function ejemplares(): HasMany
    {
        return $this->hasMany(Ejemplar::class);
    }
}
