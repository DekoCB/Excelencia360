<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Models;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Vacaciones\Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un periodo de 2 meses de vacaciones para un estudiante SIAGIE anual,
 * activado manualmente por un coordinador (ver VacacionService::activar()).
 * Es puramente informativo: no bloquea asistencia ni aula virtual.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int $matricula_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property ?int $registrado_por
 */
class Vacacion extends Model
{
    /** @use HasFactory<VacacionFactory> */
    use HasFactory;

    protected $table = 'vacaciones';

    protected $fillable = [
        'estudiante_id',
        'matricula_id',
        'fecha_inicio',
        'fecha_fin',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    protected static function newFactory(): VacacionFactory
    {
        return VacacionFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
