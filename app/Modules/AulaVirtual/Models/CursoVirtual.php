<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Database\Factories\CursoVirtualFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Se ancla a $horario_id (curso académico, un Horario por ciclo) o a
 * $curso_id (curso corto, tipo=capacitacion, sin Horario) -- exactamente
 * uno de los dos, nunca ambos ni ninguno; ver
 * CursoVirtualService::activarParaHorario()/activarParaCursoCorto().
 *
 * @property int $id
 * @property int|null $horario_id
 * @property int|null $curso_id
 * @property bool $activo
 * @property-read Horario|null $horario
 * @property-read Curso|null $curso
 */
class CursoVirtual extends Model
{
    /** @use HasFactory<CursoVirtualFactory> */
    use Auditable, HasFactory;

    protected $table = 'aula_virtual_cursos';

    protected $fillable = [
        'horario_id',
        'curso_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): CursoVirtualFactory
    {
        return CursoVirtualFactory::new();
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function esDeCursoCorto(): bool
    {
        return $this->horario_id === null;
    }

    /**
     * El Curso académico de catálogo detrás de esta aula virtual, sin
     * importar si viene vía Horario (curso académico) o directo (curso
     * corto) -- para no repetir el branch en cada vista/servicio que solo
     * necesita el nombre, la portada, etc.
     */
    public function cursoAcademico(): Curso
    {
        return $this->esDeCursoCorto() ? $this->curso : $this->horario->curso;
    }

    public function docenteId(): ?int
    {
        return $this->esDeCursoCorto() ? $this->curso->docente_id : $this->horario->docente_id;
    }

    /**
     * @return HasMany<Seccion, $this>
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Material, $this>
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('orden');
    }

    /**
     * @return HasMany<ClaseGrabada, $this>
     */
    public function clasesGrabadas(): HasMany
    {
        return $this->hasMany(ClaseGrabada::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Tarea, $this>
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    /**
     * @return HasMany<Publicacion, $this>
     */
    public function publicaciones(): HasMany
    {
        return $this->hasMany(Publicacion::class);
    }

    /**
     * @return HasMany<Foro, $this>
     */
    public function foros(): HasMany
    {
        return $this->hasMany(Foro::class);
    }

    public function esDelDocente(int $userId): bool
    {
        return $this->docenteId() === $userId;
    }
}
