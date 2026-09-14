<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Notificaciones\Database\Factories\NotificacionFactory;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notificación in-app (distinta de MensajeWhatsapp, que es saliente por
 * WhatsApp): avisos personales dentro del sistema, como "tu tarea fue
 * calificada", que el usuario ve en la campana del encabezado.
 *
 * @property int $id
 * @property int $user_id
 * @property TipoNotificacionEnum $tipo
 * @property string $titulo
 * @property string|null $url
 * @property Carbon|null $leida_en
 * @property-read User $user
 */
class Notificacion extends Model
{
    /** @use HasFactory<NotificacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'user_id',
        'tipo',
        'titulo',
        'url',
        'leida_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacionEnum::class,
            'leida_en' => 'datetime',
        ];
    }

    protected static function newFactory(): NotificacionFactory
    {
        return NotificacionFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estaLeida(): bool
    {
        return $this->leida_en !== null;
    }
}
