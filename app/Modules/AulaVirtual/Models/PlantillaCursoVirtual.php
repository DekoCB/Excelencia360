<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Copia reutilizable del contenido de un aula virtual (materiales, clases
 * grabadas, tareas y foros), guardada a nivel de Curso —no de Horario— para
 * poder aplicarla a cualquier ciclo futuro sin reconstruir todo desde cero.
 *
 * @property int $id
 * @property string $nombre
 * @property-read Curso $curso
 * @property-read User|null $creador
 */
class PlantillaCursoVirtual extends Model
{
    use Auditable;

    protected $table = 'plantillas_curso_virtual';

    protected $fillable = [
        'curso_id',
        'creado_por',
        'nombre',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * @return HasMany<PlantillaMaterial, $this>
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(PlantillaMaterial::class, 'plantilla_id')->orderBy('orden');
    }

    /**
     * @return HasMany<PlantillaClaseGrabada, $this>
     */
    public function clasesGrabadas(): HasMany
    {
        return $this->hasMany(PlantillaClaseGrabada::class, 'plantilla_id')->orderBy('orden');
    }

    /**
     * @return HasMany<PlantillaTarea, $this>
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(PlantillaTarea::class, 'plantilla_id');
    }

    /**
     * @return HasMany<PlantillaForo, $this>
     */
    public function foros(): HasMany
    {
        return $this->hasMany(PlantillaForo::class, 'plantilla_id');
    }
}
