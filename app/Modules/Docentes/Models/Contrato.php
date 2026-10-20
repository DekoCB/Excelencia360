<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Models;

use App\Models\User;
use App\Modules\Docentes\Database\Factories\ContratoFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Ya no es exclusivo de Docente (punto 10 del backlog): aplica a
 * cualquier usuario con cuenta de login -- Personal queda fuera porque,
 * a diferencia de Docente, no tiene User (ver Personal::class).
 *
 * @property int $id
 * @property int $user_id
 * @property string $tipo
 * @property Carbon $fecha_inicio
 * @property ?Carbon $fecha_fin
 * @property ?string $monto
 * @property ?string $observaciones
 * @property-read User $usuario
 */
class Contrato extends Model implements HasMedia
{
    use Auditable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'monto',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    protected static function newFactory(): ContratoFactory
    {
        return ContratoFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documento')->singleFile();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estaVigente(): bool
    {
        if ($this->fecha_fin === null) {
            return true;
        }

        return $this->fecha_fin->isFuture();
    }
}
