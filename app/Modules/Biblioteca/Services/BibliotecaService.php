<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Services;

use App\Models\User;
use App\Modules\Biblioteca\Enums\EstadoEjemplarEnum;
use App\Modules\Biblioteca\Enums\EstadoPrestamoEnum;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Libro;
use App\Modules\Biblioteca\Models\Prestamo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo (Libro/Ejemplar) y circulación (Prestamo) de la biblioteca.
 * Solo Docente/Estudiante piden prestado (tienen cuenta de acceso);
 * Personal (portería, limpieza...) no tiene user_id en el modelo actual,
 * mismo criterio ya usado en Asistencia de docentes.
 */
class BibliotecaService
{
    private const DIAS_PRESTAMO_POR_DEFECTO = 7;

    /**
     * Paginado: el catálogo crece sin límite con el tiempo, a diferencia
     * de misPrestamos()/historialDocente() (acotados a un usuario).
     */
    public function catalogo(?string $termino = null, int $perPage = 15): LengthAwarePaginator
    {
        return Libro::query()
            ->when($termino, fn ($query) => $query->where(function ($query) use ($termino) {
                $query->where('titulo', 'like', "%{$termino}%")
                    ->orWhere('autor', 'like', "%{$termino}%")
                    ->orWhere('isbn', 'like', "%{$termino}%");
            }))
            ->with('ejemplares')
            ->orderBy('titulo')
            ->paginate($perPage, ['*'], 'librosPage');
    }

    public function registrarLibro(string $titulo, string $autor, ?string $isbn, ?string $categoria, ?string $editorial, ?int $anioPublicacion): Libro
    {
        return Libro::query()->create([
            'titulo' => $titulo,
            'autor' => $autor,
            'isbn' => $isbn,
            'categoria' => $categoria,
            'editorial' => $editorial,
            'anio_publicacion' => $anioPublicacion,
        ]);
    }

    public function agregarEjemplar(Libro $libro, string $codigoInventario): Ejemplar
    {
        return $libro->ejemplares()->create([
            'codigo_inventario' => $codigoInventario,
            'estado' => EstadoEjemplarEnum::DISPONIBLE,
        ]);
    }

    /**
     * @throws ValidationException si el ejemplar no está disponible
     */
    public function prestar(Ejemplar $ejemplar, User $solicitante, User $entregadoPor, ?Carbon $fechaDevolucionEsperada = null): Prestamo
    {
        if ($ejemplar->estado !== EstadoEjemplarEnum::DISPONIBLE) {
            throw ValidationException::withMessages([
                'ejemplar' => 'Este ejemplar no está disponible para préstamo.',
            ]);
        }

        return DB::transaction(function () use ($ejemplar, $solicitante, $entregadoPor, $fechaDevolucionEsperada) {
            $prestamo = Prestamo::query()->create([
                'ejemplar_id' => $ejemplar->id,
                'solicitante_id' => $solicitante->id,
                'entregado_por' => $entregadoPor->id,
                'fecha_prestamo' => now()->format('Y-m-d'),
                'fecha_devolucion_esperada' => ($fechaDevolucionEsperada ?? now()->addDays(self::DIAS_PRESTAMO_POR_DEFECTO))->format('Y-m-d'),
                'estado' => EstadoPrestamoEnum::PRESTADO,
            ]);

            $ejemplar->update(['estado' => EstadoEjemplarEnum::PRESTADO]);

            return $prestamo;
        });
    }

    /**
     * @throws ValidationException si el préstamo ya se cerró (devuelto o perdido)
     */
    public function devolver(Prestamo $prestamo): Prestamo
    {
        if ($prestamo->estado !== EstadoPrestamoEnum::PRESTADO) {
            throw ValidationException::withMessages([
                'prestamo' => 'Este préstamo ya está cerrado.',
            ]);
        }

        DB::transaction(function () use ($prestamo) {
            $prestamo->update([
                'estado' => EstadoPrestamoEnum::DEVUELTO,
                'fecha_devolucion_real' => now()->format('Y-m-d'),
            ]);

            $prestamo->ejemplar->update(['estado' => EstadoEjemplarEnum::DISPONIBLE]);
        });

        return $prestamo->fresh();
    }

    /**
     * @throws ValidationException si el préstamo ya se cerró (devuelto o perdido)
     */
    public function marcarPerdido(Prestamo $prestamo): Prestamo
    {
        if ($prestamo->estado !== EstadoPrestamoEnum::PRESTADO) {
            throw ValidationException::withMessages([
                'prestamo' => 'Este préstamo ya está cerrado.',
            ]);
        }

        DB::transaction(function () use ($prestamo) {
            $prestamo->update(['estado' => EstadoPrestamoEnum::PERDIDO]);
            $prestamo->ejemplar->update(['estado' => EstadoEjemplarEnum::PERDIDO]);
        });

        return $prestamo->fresh();
    }

    /**
     * Paginado por la misma razón que catalogo(): crece sin límite con el
     * tiempo (a diferencia de misPrestamos(), acotado a un solicitante).
     */
    public function prestamosActivos(int $perPage = 15): LengthAwarePaginator
    {
        return Prestamo::query()
            ->where('estado', EstadoPrestamoEnum::PRESTADO)
            ->with(['ejemplar.libro', 'solicitante'])
            ->orderBy('fecha_devolucion_esperada')
            ->paginate($perPage, ['*'], 'prestamosPage');
    }

    /**
     * @return Collection<int, Prestamo>
     */
    public function misPrestamos(User $solicitante): Collection
    {
        return Prestamo::query()
            ->where('solicitante_id', $solicitante->id)
            ->with('ejemplar.libro')
            ->latest('fecha_prestamo')
            ->get();
    }
}
