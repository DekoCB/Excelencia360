<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaGeneral\Models;

use App\Models\User;
use App\Modules\AsistenciaGeneral\Database\Factories\AsistenciaGeneralFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ingreso/salida de una persona del Instituto en un turno dado -- "persona"
 * puede ser un Estudiante, un User (personal con cuenta) o un Personal
 * (portería, limpieza, etc., sin cuenta): ver la migración para por qué es
 * polimórfico en vez de 3 columnas FK nullable.
 *
 * @property int $id
 * @property string $persona_type
 * @property int $persona_id
 * @property Carbon $fecha
 * @property TurnoDiaEnum $turno
 * @property Carbon $hora_ingreso
 * @property Carbon|null $hora_salida
 * @property int|null $registrado_por
 * @property-read Estudiante|User|Personal|null $persona
 * @property-read User|null $registradoPor
 */
class AsistenciaGeneral extends Model
{
    /** @use HasFactory<AsistenciaGeneralFactory> */
    use Auditable, HasFactory;

    protected $table = 'asistencias_generales';

    protected $fillable = [
        'persona_type',
        'persona_id',
        'fecha',
        'turno',
        'hora_ingreso',
        'hora_salida',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'turno' => TurnoDiaEnum::class,
            'hora_ingreso' => 'datetime',
            'hora_salida' => 'datetime',
        ];
    }

    protected static function newFactory(): AsistenciaGeneralFactory
    {
        return AsistenciaGeneralFactory::new();
    }

    public function persona(): MorphTo
    {
        return $this->morphTo();
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estaAbierta(): bool
    {
        return $this->hora_salida === null;
    }

    /**
     * Estudiante/Personal exponen nombreCompleto(), User expone "name" --
     * unifica ambos para la vista sin que la blade tenga que distinguir
     * el tipo de "persona".
     */
    public function nombrePersona(): string
    {
        $persona = $this->persona;

        if ($persona instanceof Estudiante || $persona instanceof Personal) {
            return $persona->nombreCompleto();
        }

        if ($persona instanceof User) {
            return $persona->name;
        }

        return 'Persona eliminada';
    }
}
