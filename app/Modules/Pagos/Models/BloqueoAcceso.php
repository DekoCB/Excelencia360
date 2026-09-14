<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Database\Factories\BloqueoAccesoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property string $motivo
 * @property Carbon $fecha_bloqueo
 * @property Carbon|null $fecha_desbloqueo
 * @property bool $activo
 * @property-read Estudiante|null $estudiante
 */
class BloqueoAcceso extends Model
{
    /** @use HasFactory<BloqueoAccesoFactory> */
    use Auditable, HasFactory;

    protected $table = 'bloqueos_acceso';

    protected $fillable = [
        'estudiante_id',
        'motivo',
        'fecha_bloqueo',
        'fecha_desbloqueo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_bloqueo' => 'date',
            'fecha_desbloqueo' => 'date',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): BloqueoAccesoFactory
    {
        return BloqueoAccesoFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
