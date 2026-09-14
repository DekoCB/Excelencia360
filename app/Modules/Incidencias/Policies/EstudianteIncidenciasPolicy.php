<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Policies;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;

/**
 * Autorización sobre "¿puede este usuario reportar/ver incidencias de este
 * estudiante?". No se registra vía Gate::policy() porque Estudiante
 * pertenece al módulo Matricula; se enlaza a habilidades con nombre propio
 * desde IncidenciasServiceProvider (mismo patrón que
 * Evaluaciones\Policies\HorarioEvaluacionesPolicy).
 */
class EstudianteIncidenciasPolicy
{
    public function reportar(User $user, Estudiante $estudiante): bool
    {
        if ($user->hasRole('direccion')) {
            return true;
        }

        if ($user->hasPermissionTo('incidencias.crear')) {
            return true;
        }

        if ($user->hasPermissionTo('incidencias.gestionar_propio')) {
            return $this->esEstudianteDelDocente($user, $estudiante);
        }

        return false;
    }

    public function ver(User $user, Estudiante $estudiante): bool
    {
        if ($user->hasRole('direccion')) {
            return true;
        }

        if ($user->hasPermissionTo('incidencias.ver')) {
            return true;
        }

        if ($user->hasPermissionTo('incidencias.gestionar_propio') && $this->esEstudianteDelDocente($user, $estudiante)) {
            return true;
        }

        if ($user->hasPermissionTo('incidencias.ver_propio')) {
            return $user->estudiante?->id === $estudiante->id;
        }

        return false;
    }

    private function esEstudianteDelDocente(User $user, Estudiante $estudiante): bool
    {
        $horarios = Horario::query()->where('docente_id', $user->id)->get(['id', 'grado_id', 'ciclo_id']);

        if ($horarios->isEmpty()) {
            return false;
        }

        return Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where(function ($query) use ($horarios) {
                foreach ($horarios as $horario) {
                    $query->orWhere(fn ($query) => $query->delHorario($horario));
                }
            })
            ->exists();
    }
}
