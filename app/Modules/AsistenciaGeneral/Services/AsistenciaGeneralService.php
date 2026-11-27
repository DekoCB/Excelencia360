<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaGeneral\Services;

use App\Models\User;
use App\Modules\AsistenciaGeneral\Models\AsistenciaGeneral;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Control de ingreso/salida del Instituto (estudiantes y personal, con o
 * sin cuenta) -- ver AsistenciaGeneral para por qué "persona" es
 * polimórfico. El turno (Mañana/Tarde/Noche) se elige a mano en cada
 * registro, nunca se detecta por la hora del reloj (decisión del backlog).
 */
class AsistenciaGeneralService
{
    /**
     * Busca por nombre o DNI entre las tres fuentes de persona, unificadas
     * en una sola lista de resultados listos para mostrar.
     *
     * @return Collection<int, array{persona: Estudiante|User|Personal, tipo: string, nombre: string, dni: string}>
     */
    public function buscarPersona(string $query): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $estudiantes = Estudiante::query()
            ->where(fn ($q) => $q->where('dni', 'like', "%{$query}%")
                ->orWhere('nombres', 'like', "%{$query}%")
                ->orWhere('apellidos', 'like', "%{$query}%"))
            ->limit(10)
            ->get()
            ->map(fn (Estudiante $estudiante) => [
                'persona' => $estudiante,
                'tipo' => 'Estudiante',
                'nombre' => $estudiante->nombreCompleto(),
                'dni' => $estudiante->dni,
            ]);

        $personal = User::query()
            ->where(fn ($q) => $q->where('dni', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%"))
            ->get()
            ->filter(fn (User $user) => $user->esPersonalInstitucional())
            ->take(10)
            ->map(fn (User $user) => [
                'persona' => $user,
                'tipo' => 'Personal (con cuenta)',
                'nombre' => $user->name,
                'dni' => $user->dni ?? '—',
            ]);

        $personalSinCuenta = Personal::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('dni', 'like', "%{$query}%")
                ->orWhere('nombres', 'like', "%{$query}%")
                ->orWhere('apellidos', 'like', "%{$query}%"))
            ->limit(10)
            ->get()
            ->map(fn (Personal $persona) => [
                'persona' => $persona,
                'tipo' => $persona->cargo,
                'nombre' => $persona->nombreCompleto(),
                'dni' => $persona->dni,
            ]);

        return $estudiantes->concat($personal)->concat($personalSinCuenta)->values();
    }

    /**
     * Resuelve el QR del carnet de asistencia (mismo token que ya usan
     * Estudiante y Docente en su perfil, ver TieneQrToken) a la "persona"
     * polimórfica de este módulo -- un Docente resuelve a su User, porque
     * el registro de Asistencia General se guarda contra User, no Docente.
     * Personal (sin cuenta) no tiene QR propio: se registra por buscador.
     */
    public function personaParaQrToken(string $token): ?Model
    {
        $estudiante = Estudiante::query()->where('qr_token', $token)->first();

        if ($estudiante !== null) {
            return $estudiante;
        }

        return Docente::query()->where('qr_token', $token)->first()?->usuario;
    }

    public function registroAbiertoDe(Model $persona): ?AsistenciaGeneral
    {
        return AsistenciaGeneral::query()
            ->where('persona_type', $persona::class)
            ->where('persona_id', $persona->getKey())
            ->whereNull('hora_salida')
            ->latest('hora_ingreso')
            ->first();
    }

    public function registrarIngreso(Model $persona, TurnoDiaEnum $turno, User $registradoPor, ?string $observacion = null): AsistenciaGeneral
    {
        if ($this->registroAbiertoDe($persona) !== null) {
            throw ValidationException::withMessages([
                'busqueda' => 'Esta persona ya tiene un ingreso sin salida registrada hoy.',
            ]);
        }

        return AsistenciaGeneral::query()->create([
            'persona_type' => $persona::class,
            'persona_id' => $persona->getKey(),
            'fecha' => now()->toDateString(),
            'turno' => $turno,
            'hora_ingreso' => now(),
            'observacion' => $observacion,
            'registrado_por' => $registradoPor->id,
        ]);
    }

    public function registrarSalida(AsistenciaGeneral $registro): AsistenciaGeneral
    {
        $registro->update(['hora_salida' => now()]);

        return $registro->refresh();
    }

    /**
     * @return Collection<int, AsistenciaGeneral>
     */
    public function historialDelDia(?string $fecha = null): Collection
    {
        return AsistenciaGeneral::query()
            ->whereDate('fecha', $fecha ?? now()->toDateString())
            ->with(['persona', 'registradoPor'])
            ->latest('hora_ingreso')
            ->get();
    }
}
