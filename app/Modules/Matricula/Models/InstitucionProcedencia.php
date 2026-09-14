<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\InstitucionProcedenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $nombre_colegio
 * @property string|null $ubicacion
 * @property int|null $anio_egreso
 */
class InstitucionProcedencia extends Model
{
    /** @use HasFactory<InstitucionProcedenciaFactory> */
    use Auditable, HasFactory;

    protected $table = 'instituciones_procedencia';

    protected $fillable = [
        'estudiante_id',
        'nombre_colegio',
        'ubicacion',
        'anio_egreso',
    ];

    protected static function newFactory(): InstitucionProcedenciaFactory
    {
        return InstitucionProcedenciaFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
