<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\EstudianteFactory;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $dni
 * @property string $nombres
 * @property string $apellidos
 * @property Carbon $fecha_nacimiento
 * @property bool $es_menor_edad
 * @property string|null $celular
 * @property EstadoEstudianteEnum $estado
 * @property-read Apoderado|null $apoderado
 * @property-read InstitucionProcedencia|null $institucionProcedencia
 * @property-read User|null $user
 */
class Estudiante extends Model implements HasMedia
{
    /** @use HasFactory<EstudianteFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'user_id',
        'dni',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'es_menor_edad',
        'estado_civil',
        'direccion',
        'celular',
        'email',
        'estado',
        'grado_actual_id',
        'ciclos_completados',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'es_menor_edad' => 'boolean',
            'estado_civil' => EstadoCivilEnum::class,
            'estado' => EstadoEstudianteEnum::class,
        ];
    }

    protected static function newFactory(): EstudianteFactory
    {
        return EstudianteFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Grado, $this>
     */
    public function gradoActual(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_actual_id');
    }

    /**
     * @return HasOne<Apoderado, $this>
     */
    public function apoderado(): HasOne
    {
        return $this->hasOne(Apoderado::class);
    }

    /**
     * @return HasOne<InstitucionProcedencia, $this>
     */
    public function institucionProcedencia(): HasOne
    {
        return $this->hasOne(InstitucionProcedencia::class);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * @return HasMany<DocumentoEstudiante, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoEstudiante::class);
    }

    /**
     * @return HasMany<ExamenUbicacion, $this>
     */
    public function examenesUbicacion(): HasMany
    {
        return $this->hasMany(ExamenUbicacion::class);
    }

    /**
     * Celulares adicionales, aparte del principal (ver $celular) -- ver
     * EstudianteTelefono.
     *
     * @return HasMany<EstudianteTelefono, $this>
     */
    public function telefonos(): HasMany
    {
        return $this->hasMany(EstudianteTelefono::class);
    }

    public function nombreCompleto(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    /**
     * La foto subida en la ficha de matrícula, o si no tiene, el avatar de
     * la cuenta de acceso vinculada (la que el estudiante configura desde
     * "Mi perfil").
     */
    public function fotoUrl(): ?string
    {
        return $this->getFirstMediaUrl('foto') ?: $this->user?->getFirstMediaUrl('avatar') ?: null;
    }

    /**
     * Número al que deben llegar las comunicaciones sobre este estudiante:
     * el del apoderado si es menor de edad y lo tiene registrado, si no el
     * propio.
     */
    public function telefonoDeContacto(): ?string
    {
        if ($this->es_menor_edad && $this->apoderado?->celular) {
            return $this->apoderado->celular;
        }

        return $this->celular;
    }
}
