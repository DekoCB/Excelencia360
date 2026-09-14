<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Database\Factories\MensajeWhatsappFactory;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Pagos\Models\Cuota;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $campania_id
 * @property int|null $estudiante_id
 * @property int|null $cuota_id
 * @property string $telefono
 * @property string $contenido
 * @property TipoMensajeWhatsappEnum $tipo
 * @property EstadoMensajeWhatsappEnum $estado
 * @property string|null $external_id
 * @property string|null $error
 * @property Carbon|null $enviado_en
 * @property-read CampaniaWhatsapp|null $campania
 * @property-read Estudiante|null $estudiante
 * @property-read Cuota|null $cuota
 */
class MensajeWhatsapp extends Model
{
    /** @use HasFactory<MensajeWhatsappFactory> */
    use Auditable, HasFactory;

    protected $table = 'mensajes_whatsapp';

    protected $fillable = [
        'campania_id',
        'estudiante_id',
        'cuota_id',
        'telefono',
        'contenido',
        'tipo',
        'estado',
        'external_id',
        'error',
        'enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMensajeWhatsappEnum::class,
            'estado' => EstadoMensajeWhatsappEnum::class,
            'enviado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): MensajeWhatsappFactory
    {
        return MensajeWhatsappFactory::new();
    }

    public function campania(): BelongsTo
    {
        return $this->belongsTo(CampaniaWhatsapp::class, 'campania_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
