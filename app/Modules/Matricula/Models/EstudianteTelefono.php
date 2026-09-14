<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un celular adicional de un estudiante, aparte de Estudiante::celular
 * (el principal, que no se toca).
 *
 * @property int $id
 * @property int $estudiante_id
 * @property string $numero
 */
class EstudianteTelefono extends Model
{
    use Auditable;

    protected $fillable = [
        'estudiante_id',
        'numero',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
