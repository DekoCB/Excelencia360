<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Models\User;
use App\Modules\AulaVirtual\Database\Factories\PublicacionFactory;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property TipoPublicacionEnum $tipo
 * @property string $contenido
 */
class Publicacion extends Model
{
    /** @use HasFactory<PublicacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'publicaciones';

    protected $fillable = [
        'curso_virtual_id',
        'autor_id',
        'tipo',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPublicacionEnum::class,
        ];
    }

    protected static function newFactory(): PublicacionFactory
    {
        return PublicacionFactory::new();
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
     * @return MorphMany<Comentario, $this>
     */
    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'commentable')->latest();
    }
}
