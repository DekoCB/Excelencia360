<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\ProgramaEstudioFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class ProgramaEstudio extends Model
{
    /** @use HasFactory<ProgramaEstudioFactory> */
    use Auditable, HasFactory;

    protected $table = 'programas_estudio';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): ProgramaEstudioFactory
    {
        return ProgramaEstudioFactory::new();
    }

    public function grados(): HasMany
    {
        return $this->hasMany(Grado::class);
    }
}
