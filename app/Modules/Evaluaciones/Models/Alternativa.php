<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Evaluaciones\Database\Factories\AlternativaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pregunta_id
 * @property string $texto
 * @property bool $es_correcta
 * @property int $orden
 * @property-read Pregunta $pregunta
 */
class Alternativa extends Model
{
    /** @use HasFactory<AlternativaFactory> */
    use Auditable, HasFactory;

    protected $table = 'alternativas';

    protected $fillable = [
        'pregunta_id',
        'texto',
        'es_correcta',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_correcta' => 'boolean',
        ];
    }

    protected static function newFactory(): AlternativaFactory
    {
        return AlternativaFactory::new();
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }
}
