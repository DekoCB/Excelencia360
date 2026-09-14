<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Models\User;
use App\Modules\AulaVirtual\Database\Factories\ComentarioFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $contenido
 * @property-read User $autor
 */
class Comentario extends Model
{
    /** @use HasFactory<ComentarioFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'autor_id',
        'contenido',
    ];

    protected static function newFactory(): ComentarioFactory
    {
        return ComentarioFactory::new();
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
