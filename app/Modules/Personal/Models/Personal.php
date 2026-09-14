<?php

declare(strict_types=1);

namespace App\Modules\Personal\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Personal\Database\Factories\PersonalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Directorio de personal adicional de la institución (portería, limpieza,
 * psicología, secretaría, etc.) que no necesita iniciar sesión en el
 * sistema -- a diferencia de Docente, no extiende a un User.
 *
 * @property int $id
 * @property string $nombres
 * @property string $apellidos
 * @property string $dni
 * @property ?string $celular
 * @property string $cargo
 * @property ?string $area
 * @property ?Carbon $fecha_ingreso
 * @property bool $activo
 */
class Personal extends Model
{
    use Auditable, HasFactory;

    protected $table = 'personal';

    protected $fillable = [
        'nombres',
        'apellidos',
        'dni',
        'celular',
        'cargo',
        'area',
        'fecha_ingreso',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): PersonalFactory
    {
        return PersonalFactory::new();
    }

    public function nombreCompleto(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
