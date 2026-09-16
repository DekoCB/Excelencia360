<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Modules\Certificados\Database\Factories\CursoCapacitacionFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de cursos de capacitación (Ofimática, etc. -- distinto de
 * Academico\Curso, que es una materia del currículo EBA con horario
 * propio) para los que se emite un Certificado de tipo
 * certificado_capacitacion. documento_autorizacion es la resolución que
 * autoriza a la institución a certificar ESE curso (ej. "R.D.R.
 * N°2182-2023-DREP"), no cambia por certificado individual.
 *
 * @property int $id
 * @property string $nombre
 * @property int $horas_lectivas
 * @property string|null $documento_autorizacion
 */
class CursoCapacitacion extends Model
{
    /** @use HasFactory<CursoCapacitacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'cursos_capacitacion';

    protected $fillable = [
        'nombre',
        'horas_lectivas',
        'documento_autorizacion',
    ];

    protected static function newFactory(): CursoCapacitacionFactory
    {
        return CursoCapacitacionFactory::new();
    }

    /**
     * @return HasMany<Certificado, $this>
     */
    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }
}
