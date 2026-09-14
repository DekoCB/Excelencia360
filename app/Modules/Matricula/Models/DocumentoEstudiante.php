<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\DocumentoEstudianteFactory;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property TipoDocumentoEnum $tipo
 * @property bool $verificado
 */
class DocumentoEstudiante extends Model implements HasMedia
{
    /** @use HasFactory<DocumentoEstudianteFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'documentos_estudiante';

    protected $fillable = [
        'estudiante_id',
        'tipo',
        'subido_por',
        'verificado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoEnum::class,
            'verificado' => 'boolean',
        ];
    }

    protected static function newFactory(): DocumentoEstudianteFactory
    {
        return DocumentoEstudianteFactory::new();
    }

    /**
     * 'archivo' es la cara (o el único archivo, para documentos que no son
     * DNI). 'reverso' solo se usa para DNI_ESTUDIANTE/DNI_APODERADO -- el
     * sello del otro lado, opcional, para poder armar un PDF con ambas
     * caras (ver DocumentoEstudianteService::generarPdfDni()).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')->singleFile();
        $this->addMediaCollection('reverso')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
