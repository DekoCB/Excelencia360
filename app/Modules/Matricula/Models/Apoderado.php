<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\ApoderadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por hijo (`estudiante_id` es unique): la misma persona con dos
 * hijos matriculados tiene dos filas de Apoderado, una por cada uno. No
 * hay un identificador único de "la persona apoderado" aparte del DNI --
 * `user_id` es lo que junta esas filas bajo una sola cuenta del portal
 * (ver MatriculaService::registrarApoderado() y User::apoderados()).
 *
 * @property int $estudiante_id
 * @property string $nombres
 * @property string $dni
 * @property string $celular
 * @property string|null $correo
 * @property string|null $direccion
 * @property string $parentesco
 * @property int|null $user_id
 */
class Apoderado extends Model
{
    /** @use HasFactory<ApoderadoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'estudiante_id',
        'user_id',
        'nombres',
        'dni',
        'celular',
        'correo',
        'direccion',
        'parentesco',
    ];

    protected static function newFactory(): ApoderadoFactory
    {
        return ApoderadoFactory::new();
    }

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
