<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Notificaciones\Database\Factories\CampaniaWhatsappFactory;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property int $plantilla_id
 * @property array<string, mixed> $segmento
 * @property EstadoCampaniaEnum $estado
 * @property int $total_destinatarios
 * @property int $enviados
 * @property int $fallidos
 * @property int $creado_por
 * @property-read PlantillaWhatsapp $plantilla
 * @property-read User $creador
 */
class CampaniaWhatsapp extends Model
{
    /** @use HasFactory<CampaniaWhatsappFactory> */
    use Auditable, HasFactory;

    protected $table = 'campanias_whatsapp';

    protected $fillable = [
        'nombre',
        'plantilla_id',
        'segmento',
        'estado',
        'total_destinatarios',
        'enviados',
        'fallidos',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'segmento' => 'array',
            'estado' => EstadoCampaniaEnum::class,
        ];
    }

    protected static function newFactory(): CampaniaWhatsappFactory
    {
        return CampaniaWhatsappFactory::new();
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaWhatsapp::class, 'plantilla_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class, 'campania_id');
    }
}
