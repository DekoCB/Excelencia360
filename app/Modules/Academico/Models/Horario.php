<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Models\User;
use App\Modules\Academico\Database\Factories\HorarioFactory;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ciclo_id y grado_id son null cuando es un horario de curso corto (sin
 * Período de matrícula ni Semestre, ver esDeCursoCorto()) -- null en
 * ambos es la señal, igual que CursoVirtual::esDeCursoCorto() usa
 * horario_id null.
 *
 * @property int $id
 * @property int $curso_id
 * @property int $docente_id
 * @property int $aula_id
 * @property int|null $ciclo_id
 * @property int|null $grado_id
 * @property-read Curso $curso
 * @property-read User $docente
 * @property-read Aula $aula
 * @property-read Grado|null $grado
 * @property-read Collection<int, HorarioDia> $dias
 */
class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'curso_id',
        'docente_id',
        'aula_id',
        'ciclo_id',
        'grado_id',
    ];

    protected static function newFactory(): HorarioFactory
    {
        return HorarioFactory::new();
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    /**
     * @return HasMany<HorarioDia, $this>
     */
    public function dias(): HasMany
    {
        return $this->hasMany(HorarioDia::class)->orderBy('id');
    }

    /**
     * A qué franja institucional (Lunes a Viernes, Sábados y Domingos)
     * corresponde este horario, o null si sus días no calzan con ninguna
     * (por ejemplo, un registro antiguo con otra combinación de días --
     * incluidas las franjas institucionales anteriores a este cambio).
     */
    public function franja(): ?FranjaHorarioEnum
    {
        return FranjaHorarioEnum::desdeDias($this->dias->pluck('dia_semana'));
    }

    public function esDeCursoCorto(): bool
    {
        return $this->ciclo_id === null;
    }

    /**
     * Los horarios que corresponden a una matrícula: mismo grado y ciclo.
     * Complementa a Matricula::scopeDelHorario(), la misma regla vista
     * desde el otro lado.
     */
    public function scopeDeLaMatricula(Builder $query, Matricula $matricula): Builder
    {
        return $query->where('grado_id', $matricula->grado_id)->where('ciclo_id', $matricula->ciclo_id);
    }

    /**
     * El día (con su horario propio) que corresponde a esta fecha, o null
     * si este horario no dicta clase ese día de la semana.
     */
    public function diaParaFecha(Carbon $fecha): ?HorarioDia
    {
        return $this->dias->first(
            fn (HorarioDia $dia) => $dia->dia_semana->numeroCarbon() === $fecha->dayOfWeek,
        );
    }

    /**
     * Resumen legible de los días y horarios, p. ej. "Lunes y Miércoles
     * 18:00–20:00" cuando todos comparten el mismo horario, o
     * "Lunes 18:00–20:00, Martes 16:00–18:00" cuando difieren.
     */
    public function diasResumen(): string
    {
        $dias = $this->dias;

        if ($dias->isEmpty()) {
            return '—';
        }

        $primero = $dias->first();
        $mismoHorario = $dias->every(
            fn (HorarioDia $dia) => $dia->hora_inicio === $primero->hora_inicio && $dia->hora_fin === $primero->hora_fin,
        );

        if ($primero->hora_inicio === null) {
            return $this->nombresDias($dias);
        }

        if ($mismoHorario) {
            return $this->nombresDias($dias).' '.$this->rangoHorario($primero);
        }

        return $dias
            ->map(fn (HorarioDia $dia) => $dia->dia_semana->label().' '.$this->rangoHorario($dia))
            ->implode(', ');
    }

    /**
     * @param  Collection<int, HorarioDia>  $dias
     */
    private function nombresDias(Collection $dias): string
    {
        $nombres = $dias->map(fn (HorarioDia $dia) => $dia->dia_semana->label());

        if ($nombres->count() === 1) {
            return $nombres->first();
        }

        return $nombres->slice(0, -1)->implode(', ').' y '.$nombres->last();
    }

    private function rangoHorario(HorarioDia $dia): string
    {
        if ($dia->hora_inicio === null || $dia->hora_fin === null) {
            return 'sin hora definida';
        }

        return substr($dia->hora_inicio, 0, 5).'–'.substr($dia->hora_fin, 0, 5);
    }
}
