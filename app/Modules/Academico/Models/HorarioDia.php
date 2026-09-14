<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un día concreto (con su propio horario) en que se dicta un Horario. Un
 * Horario tiene una fila de estas por cada día en que se reúne.
 *
 * @property int $id
 * @property int $horario_id
 * @property DiaSemanaEnum $dia_semana
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property-read Horario $horario
 */
class HorarioDia extends Model
{
    use Auditable;

    protected $fillable = [
        'horario_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => DiaSemanaEnum::class,
        ];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }
}
