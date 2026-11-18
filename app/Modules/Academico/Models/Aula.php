<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\AulaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un aula, opcionalmente atada a un Grupo (Ciclo) y una letra (A/B): las
 * aulas "de grupo" son las que alojan a los grados de ese grupo (A =
 * grados 1-2, B = grados 3-4). ciclo_id/letra son nullable para no romper
 * aulas sueltas creadas antes de este esquema. sede_id agrupa las aulas
 * por sede física (solo para mostrarlas organizadas; Horario::aula_id
 * sigue apuntando directo a esta misma Aula para el control de choques).
 *
 * @property int $id
 * @property int|null $sede_id
 * @property int|null $ciclo_id
 * @property string|null $letra
 * @property string $nombre
 * @property-read Ciclo|null $ciclo
 * @property-read Sede|null $sede
 */
class Aula extends Model
{
    /** @use HasFactory<AulaFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'sede_id',
        'ciclo_id',
        'letra',
        'nombre',
        'capacidad',
        'ubicacion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): AulaFactory
    {
        return AulaFactory::new();
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
