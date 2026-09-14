<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Policies;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Matricula;

/**
 * Autorización sobre "¿puede este usuario ver/registrar evaluaciones de
 * este horario?". Igual que en Asistencia, no se registra vía
 * Gate::policy() porque Horario pertenece al módulo Académico; se enlaza a
 * habilidades con nombre propio desde EvaluacionesServiceProvider.
 */
class HorarioEvaluacionesPolicy
{
    public function registrar(User $user, Horario $horario): bool
    {
        if ($user->hasRole('direccion')) {
            return true;
        }

        return $user->hasPermissionTo('evaluaciones.registrar') && $horario->docente_id === $user->id;
    }

    public function ver(User $user, Horario $horario): bool
    {
        if ($user->hasPermissionTo('evaluaciones.ver')) {
            return true;
        }

        if ($this->registrar($user, $horario)) {
            return true;
        }

        return $this->estudianteMatriculado($user, $horario);
    }

    /**
     * Solo cuenta como matriculado el estudiante con una matrícula
     * aprobada en el mismo grado y ciclo del horario (ver
     * Matricula::scopeDelHorario()).
     */
    private function estudianteMatriculado(User $user, Horario $horario): bool
    {
        if (! $user->hasPermissionTo('evaluaciones.ver_propio')) {
            return false;
        }

        $estudiante = $user->estudiante;

        if (! $estudiante) {
            return false;
        }

        return Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->delHorario($horario)
            ->exists();
    }
}
