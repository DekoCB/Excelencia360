<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Identidad\DTOs\SesionActiva;
use App\Modules\Identidad\Models\RegistroIngreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona las sesiones activas guardadas en la tabla `sessions`
 * (SESSION_DRIVER=database). Revocar una sesión aquí la invalida en la
 * siguiente petición de ese navegador, sin esperar a que expire.
 *
 * También lleva el registro de "quién entró" (RegistroIngreso): varias
 * cuentas institucionales las usa más de una persona, así que la cuenta
 * sola no basta para saber quién estuvo detrás de una sesión -- el nombre
 * que se escribe en el login sí.
 */
class SessionControlService
{
    /**
     * @return Collection<int, SesionActiva>
     */
    public function sesionesDe(User $usuario, string $sesionActualId): Collection
    {
        $sesiones = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->orderByDesc('last_activity')
            ->get();

        $nombresPorSesion = RegistroIngreso::query()
            ->where('user_id', $usuario->id)
            ->whereIn('session_id', $sesiones->pluck('id'))
            ->latest('iniciado_en')
            ->get()
            ->unique('session_id')
            ->pluck('nombre', 'session_id');

        return $sesiones->map(fn ($sesion) => new SesionActiva(
            id: (string) $sesion->id,
            ipAddress: $sesion->ip_address,
            userAgent: $sesion->user_agent,
            lastActivity: (int) $sesion->last_activity,
            esActual: $sesion->id === $sesionActualId,
            nombre: $nombresPorSesion->get($sesion->id),
        ));
    }

    public function revocar(string $sesionId): void
    {
        $this->finalizarIngreso($sesionId);

        DB::table('sessions')->where('id', $sesionId)->delete();
    }

    public function revocarTodasMenosActual(User $usuario, string $sesionActualId): void
    {
        $sesionesARevocar = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $sesionActualId)
            ->pluck('id');

        foreach ($sesionesARevocar as $sesionId) {
            $this->finalizarIngreso($sesionId);
        }

        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $sesionActualId)
            ->delete();
    }

    /**
     * A diferencia de revocarTodasMenosActual(), sin excepción: la usa
     * UserManagementService::actualizar() cuando un administrador desactiva
     * a OTRO usuario, así que no hay "sesión actual" propia que conservar.
     */
    public function revocarTodas(User $usuario): void
    {
        $sesiones = DB::table('sessions')->where('user_id', $usuario->id)->pluck('id');

        foreach ($sesiones as $sesionId) {
            $this->finalizarIngreso($sesionId);
        }

        DB::table('sessions')->where('user_id', $usuario->id)->delete();
    }

    /**
     * Abre un nuevo registro de ingreso -- se llama justo cuando el login
     * termina de verdad (ver pages/auth/login.blade.php y
     * two-factor-challenge.blade.php, los dos únicos puntos donde
     * Auth::login() queda confirmado).
     */
    public function registrarIngreso(User $usuario, string $nombre, string $sessionId, ?string $ip): RegistroIngreso
    {
        return RegistroIngreso::query()->create([
            'user_id' => $usuario->id,
            'session_id' => $sessionId,
            'nombre' => $nombre,
            'ip_address' => $ip,
            'iniciado_en' => now(),
        ]);
    }

    /**
     * Cierra el registro de ingreso abierto para esa sesión (si hay uno):
     * al cerrar sesión explícitamente, o cuando la sesión se revoca por
     * cualquiera de los métodos de arriba.
     */
    public function finalizarIngreso(string $sessionId): void
    {
        RegistroIngreso::query()
            ->where('session_id', $sessionId)
            ->whereNull('finalizado_en')
            ->update(['finalizado_en' => now()]);
    }

    /**
     * Total de horas ya registradas (ingresos ya cerrados, no los que
     * siguen en curso) por cada nombre distinto que usó esta cuenta --
     * para que Dirección vea cuánto tiempo ingresó cada quien, aunque
     * varias personas compartan el mismo login.
     *
     * @return Collection<int, array{nombre: string, horas: float, ingresos: int<0, max>}>
     */
    public function horasPorNombre(User $usuario): Collection
    {
        return RegistroIngreso::query()
            ->where('user_id', $usuario->id)
            ->whereNotNull('finalizado_en')
            ->get()
            ->groupBy('nombre')
            ->map(fn (Collection $registros, string $nombre) => [
                'nombre' => $nombre,
                'horas' => round($registros->sum(fn (RegistroIngreso $r) => $r->duracionEnMinutos()) / 60, 1),
                'ingresos' => $registros->count(),
            ])
            ->sortByDesc('horas')
            ->values();
    }
}
