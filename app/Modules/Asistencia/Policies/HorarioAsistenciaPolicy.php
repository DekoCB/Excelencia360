<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Policies;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Matricula;

/**
 * Autorización sobre "¿puede este usuario ver/registrar la asistencia de
 * este horario?". No se registra vía Gate::policy() porque Horario
 * pertenece al módulo Académico y ya podría tener su propia policy de
 * edición allí; en su lugar se enlaza a habilidades con nombre propio
 * desde AsistenciaServiceProvider.
 */
class HorarioAsistenciaPolicy
{
    public function registrar(User $user, Horario $horario): bool
    {
        if ($user->hasRole('direccion')) {
            return true;
        }

        return $user->hasPermissionTo('asistencia.registrar') && $horario->docente_id === $user->id;
    }

    public function ver(User $user, Horario $horario): bool
    {
        if ($user->hasPermissionTo('asistencia.ver')) {
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
        if (! $user->hasPermissionTo('asistencia.ver_propio')) {
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
