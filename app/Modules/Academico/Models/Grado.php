<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\GradoFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $programa_estudio_id
 * @property string $nombre
 * @property int $orden
 */
class Grado extends Model
{
    /** @use HasFactory<GradoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'programa_estudio_id',
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

    public function programaEstudio(): BelongsTo
    {
        return $this->belongsTo(ProgramaEstudio::class);
    }

    /**
     * @return BelongsToMany<Curso, $this>
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_grado');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * A qué aula (Grupo A/B) corresponde este grado dentro de un mismo
     * Grupo/Ciclo: los dos primeros grados (por orden) van al Aula A, los
     * siguientes dos al Aula B. Uso interno de DemoRobustoSeeder para
     * repartir cursos entre las aulas físicas sin chocar -- ya no es un
     * concepto que se muestre ni se filtre desde la interfaz (un Instituto
     * solo se organiza por carrera y semestre).
     */
    public function letraAula(): string
    {
        return $this->orden <= 2 ? 'A' : 'B';
    }
}
