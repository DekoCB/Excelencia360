<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Models;

use App\Models\User;
use App\Modules\Calendario\Database\Factories\EventoCalendarioFactory;
use App\Modules\Calendario\Enums\TipoEventoEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $descripcion
 * @property TipoEventoEnum $tipo
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property string|null $hora_inicio
 * @property string|null $hora_fin
 * @property int|null $creado_por
 */
class EventoCalendario extends Model
{
    /** @use HasFactory<EventoCalendarioFactory> */
    use Auditable, HasFactory;

    protected $table = 'eventos_calendario';

    protected $fillable = [
        'titulo',
        'descripcion',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'hora_inicio',
        'hora_fin',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoEnum::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    protected static function newFactory(): EventoCalendarioFactory
    {
        return EventoCalendarioFactory::new();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
