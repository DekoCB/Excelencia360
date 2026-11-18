<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\SedeFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $direccion
 * @property bool $activa
 * @property-read Collection<int, Aula> $aulas
 */
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use Auditable, HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'direccion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): SedeFactory
    {
        return SedeFactory::new();
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class);
    }
}
