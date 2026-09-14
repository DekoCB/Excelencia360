<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $titulo
 * @property string|null $descripcion
 * @property int|null $semana
 */
class PlantillaForo extends Model
{
    use Auditable;

    protected $table = 'plantilla_foros';

    protected $fillable = [
        'plantilla_id',
        'semana',
        'titulo',
        'descripcion',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCursoVirtual::class, 'plantilla_id');
    }
}
