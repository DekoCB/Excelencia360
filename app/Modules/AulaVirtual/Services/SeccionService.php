<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Seccion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeccionService
{
    /**
     * @return Collection<int, Seccion>
     */
    public function listarPorCurso(CursoVirtual $curso): Collection
    {
        return $curso->secciones()->orderBy('orden')->get();
    }

    /**
     * @throws ValidationException si no se da ni nombre ni fecha
     */
    public function crear(CursoVirtual $curso, ?string $nombre, ?string $fecha): Seccion
    {
        $this->validarNombreOFecha($nombre, $fecha);

        return $curso->secciones()->create([
            'nombre' => $nombre,
            'fecha' => $fecha,
            'orden' => $curso->secciones()->count(),
        ]);
    }

    /**
     * @throws ValidationException si no se da ni nombre ni fecha
     */
    public function actualizar(Seccion $seccion, ?string $nombre, ?string $fecha): Seccion
    {
        $this->validarNombreOFecha($nombre, $fecha);

        $seccion->update(['nombre' => $nombre, 'fecha' => $fecha]);

        return $seccion->fresh();
    }

    /**
     * Resuelve, en $curso, la sección equivalente a $origen (mismo
     * nombre y fecha) -- creándola si no existe todavía. Usado por
     * "crear para varios cursos a la vez" y por "aplicar plantilla": una
     * Sección pertenece a un solo curso virtual, así que no se puede
     * reutilizar el mismo seccion_id en otro curso sin más, a diferencia
     * del entero "semana" que reemplaza (ese sí era compartible tal
     * cual, al no tener dueño).
     */
    public function obtenerOCrearEquivalente(CursoVirtual $curso, ?Seccion $origen): ?Seccion
    {
        if ($origen === null) {
            return null;
        }

        $existente = $curso->secciones()
            ->where('nombre', $origen->nombre)
            ->where('fecha', $origen->fecha?->format('Y-m-d'))
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return $curso->secciones()->create([
            'nombre' => $origen->nombre,
            'fecha' => $origen->fecha,
            'orden' => $curso->secciones()->count(),
        ]);
    }

    /**
     * Igual que obtenerOCrearEquivalente(), pero a partir de un nombre
     * suelto (sin fecha) -- lo que guardan las plantillas
     * (nombre_seccion, ver PlantillaMaterial y hermanas) al no tener un
     * curso virtual real del que copiar una Seccion completa.
     */
    public function obtenerOCrearPorNombre(CursoVirtual $curso, ?string $nombre): ?Seccion
    {
        if ($nombre === null || trim($nombre) === '') {
            return null;
        }

        $existente = $curso->secciones()->where('nombre', $nombre)->whereNull('fecha')->first();

        if ($existente !== null) {
            return $existente;
        }

        return $curso->secciones()->create([
            'nombre' => $nombre,
            'fecha' => null,
            'orden' => $curso->secciones()->count(),
        ]);
    }

    /**
     * El contenido que tenía esta sección no se borra: queda con
     * seccion_id null (ver nullOnDelete en las migraciones), y vuelve a
     * aparecer bajo "Bienvenida" -- el mismo comportamiento que ya tenía
     * el contenido sin clasificar antes de esta sección existir.
     */
    public function eliminar(Seccion $seccion): void
    {
        $seccion->delete();
    }

    public function moverArriba(Seccion $seccion): void
    {
        $this->intercambiarOrden($seccion, anterior: true);
    }

    public function moverAbajo(Seccion $seccion): void
    {
        $this->intercambiarOrden($seccion, anterior: false);
    }

    private function intercambiarOrden(Seccion $seccion, bool $anterior): void
    {
        $vecina = Seccion::query()
            ->where('curso_virtual_id', $seccion->curso_virtual_id)
            ->where('orden', $anterior ? '<' : '>', $seccion->orden)
            ->orderBy('orden', $anterior ? 'desc' : 'asc')
            ->first();

        if ($vecina === null) {
            return;
        }

        DB::transaction(function () use ($seccion, $vecina) {
            $ordenSeccion = $seccion->orden;
            $seccion->update(['orden' => $vecina->orden]);
            $vecina->update(['orden' => $ordenSeccion]);
        });
    }

    private function validarNombreOFecha(?string $nombre, ?string $fecha): void
    {
        if (($nombre === null || trim($nombre) === '') && $fecha === null) {
            throw ValidationException::withMessages([
                'nombre' => 'Una sección necesita un nombre, una fecha, o ambos.',
            ]);
        }
    }
}
