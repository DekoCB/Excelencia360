<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\GradoFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property int $orden
 */
class Grado extends Model
{
    /** @use HasFactory<GradoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): GradoFactory
    {
        return GradoFactory::new();
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * A qué aula (Grupo A/B) corresponde este grado dentro de un mismo
     * Grupo/Ciclo: los dos primeros grados (por orden) van al Aula A, los
     * siguientes dos al Aula B. Ya no es una elección libre por curso --
     * ver la migración 2027_01_07_090100 que retira Matricula.seccion.
     */
    public function letraAula(): string
    {
        return $this->orden <= 2 ? 'A' : 'B';
    }

    /**
     * Filtra por la misma letra que calcula letraAula(), para módulos (ej.
     * Migraciones) que necesitan acotar una consulta por sección A/B en vez
     * de solo mostrarla.
     *
     * @param  Builder<Grado>  $query
     * @return Builder<Grado>
     */
    public function scopeDeSeccion(Builder $query, string $letra): Builder
    {
        return $letra === 'A' ? $query->where('orden', '<=', 2) : $query->where('orden', '>', 2);
    }
}
