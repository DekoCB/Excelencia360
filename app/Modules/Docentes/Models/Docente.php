<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Models;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Docentes\Database\Factories\DocenteFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ficha del docente: complementa a su User (nombre, DNI, teléfono, estado
 * de la cuenta siguen viviendo ahí) con los datos propios de la función
 * docente. Los cursos que dicta no se guardan acá -- se derivan de
 * Horario.docente_id (que sigue apuntando a users.id, sin cambios, para no
 * tocar Academico/AulaVirtual/Evaluaciones/Asistencia), así nunca quedan
 * desincronizados de la asignación real.
 *
 * @property int $id
 * @property int $user_id
 * @property ?string $especialidad
 * @property ?string $grado_academico
 * @property ?Carbon $fecha_ingreso
 * @property-read User $usuario
 */
class Docente extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'especialidad',
        'grado_academico',
        'fecha_ingreso',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
        ];
    }

    protected static function newFactory(): DocenteFactory
    {
        return DocenteFactory::new();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'docente_id', 'user_id');
    }
}
