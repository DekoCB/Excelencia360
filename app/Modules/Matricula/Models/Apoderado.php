<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\ApoderadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $nombres
 * @property string $dni
 * @property string $celular
 * @property string|null $correo
 * @property string|null $direccion
 * @property string $parentesco
 */
class Apoderado extends Model
{
    /** @use HasFactory<ApoderadoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'estudiante_id',
        'nombres',
        'dni',
        'celular',
        'correo',
        'direccion',
        'parentesco',
    ];

    protected static function newFactory(): ApoderadoFactory
    {
        return ApoderadoFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
