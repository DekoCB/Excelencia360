<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Models\User;
use App\Modules\AulaVirtual\Database\Factories\ForoRespuestaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $contenido
 */
class ForoRespuesta extends Model
{
    /** @use HasFactory<ForoRespuestaFactory> */
    use Auditable, HasFactory;

    protected $table = 'foro_respuestas';

    protected $fillable = [
        'foro_id',
        'autor_id',
        'contenido',
    ];

    protected static function newFactory(): ForoRespuestaFactory
    {
        return ForoRespuestaFactory::new();
    }

    public function foro(): BelongsTo
    {
        return $this->belongsTo(Foro::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
