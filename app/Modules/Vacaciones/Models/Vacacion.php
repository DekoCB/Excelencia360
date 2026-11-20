<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Models;

use App\Models\User;
use App\Modules\Vacaciones\Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un periodo de vacaciones de un miembro del personal (docente,
 * administrativo, etc.), activado manualmente por un coordinador/dirección
 * (ver VacacionService::activar()). Es puramente informativo: no bloquea
 * asistencia ni aula virtual.
 *
 * @property int $id
 * @property int $user_id
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
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
