<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Models\User;
use App\Modules\AulaVirtual\Database\Factories\ForoFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $titulo
 * @property string|null $descripcion
 * @property int|null $semana
 */
class Foro extends Model
{
    /** @use HasFactory<ForoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'curso_virtual_id',
        'semana',
        'autor_id',
        'titulo',
        'descripcion',
    ];

    protected static function newFactory(): ForoFactory
    {
        return ForoFactory::new();
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * @return HasMany<ForoRespuesta, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(ForoRespuesta::class)->oldest();
    }
}
