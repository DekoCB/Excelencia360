<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\CertificadoSubidoFactory;
use App\Modules\Matricula\Enums\TipoCertificadoSubidoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * El documento oficial (escaneado) de un certificado de estudios o de
 * capacitación, subido desde la ficha del estudiante -- a diferencia del
 * PDF autogenerado de App\Modules\Certificados\Models\Certificado, este
 * es el archivo real con validez legal. Un estudiante puede tener varios
 * (uno por curso de capacitación, o varios de estudios), por eso es un
 * modelo aparte de DocumentoEstudiante (cuyas colecciones son singleFile
 * por tipo fijo).
 *
 * @property int $id
 * @property TipoCertificadoSubidoEnum $tipo
 * @property int|null $curso_id
 * @property-read Curso|null $curso
 */
class CertificadoSubido extends Model implements HasMedia
{
    /** @use HasFactory<CertificadoSubidoFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'certificados_subidos';

    protected $fillable = [
        'estudiante_id',
        'tipo',
        'curso_id',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCertificadoSubidoEnum::class,
        ];
    }

    protected static function newFactory(): CertificadoSubidoFactory
    {
        return CertificadoSubidoFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
