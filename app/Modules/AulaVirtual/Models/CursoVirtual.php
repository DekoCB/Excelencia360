<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Database\Factories\CursoVirtualFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property bool $activo
 * @property-read Horario $horario
 */
class CursoVirtual extends Model
{
    /** @use HasFactory<CursoVirtualFactory> */
    use Auditable, HasFactory;

    protected $table = 'aula_virtual_cursos';

    protected $fillable = [
        'horario_id',
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
        return $this->horario->docente_id === $userId;
    }
}
