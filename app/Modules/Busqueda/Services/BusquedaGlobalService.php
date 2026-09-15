<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Services;

use App\Models\User;
use App\Modules\Busqueda\DTOs\ResultadoBusquedaData;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use Illuminate\Support\Collection;

/**
 * Busca por nombre/DNI a través de las entidades que hoy solo se pueden
 * buscar cada una por separado dentro de su propio módulo (ver auditoría
 * §25). No es un motor de búsqueda nuevo: son las mismas consultas LIKE
 * que ya usa cada índice, unidas en un solo resultado y filtradas por los
 * mismos permisos *.ver que ya gobiernan el acceso a cada una -- no se
 * inventó un permiso busqueda.* nuevo.
 *
 * Docente y Personal no tienen una página propia para "ir directo a la
 * ficha" (se editan desde un modal en su índice, no una ruta dedicada),
 * así que su resultado enlaza al índice con el DNI como query string
 * (?q=...), que ese índice ya sabe usar para prefiltrar su propia lista.
 */
class BusquedaGlobalService
{
    private const MINIMO_CARACTERES = 2;

    private const LIMITE_POR_TIPO = 5;

    /**
     * @return Collection<int, ResultadoBusquedaData>
     */
    public function buscar(User $usuario, string $termino): Collection
    {
        $termino = trim($termino);

        if (mb_strlen($termino) < self::MINIMO_CARACTERES) {
            return collect();
        }

        return collect()
            ->merge($usuario->hasPermissionTo('matricula.ver') ? $this->buscarEstudiantes($termino) : [])
            ->merge($usuario->hasPermissionTo('matricula.ver') ? $this->buscarApoderados($termino) : [])
            ->merge($usuario->hasPermissionTo('docentes.ver') ? $this->buscarDocentes($termino) : [])
            ->merge($usuario->hasPermissionTo('personal.ver') ? $this->buscarPersonal($termino) : [])
            ->values();
    }

    /**
     * @return Collection<int, ResultadoBusquedaData>
     */
    private function buscarEstudiantes(string $termino): Collection
    {
        return Estudiante::query()
            ->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            })
            ->limit(self::LIMITE_POR_TIPO)
            ->get()
            ->map(fn (Estudiante $estudiante) => new ResultadoBusquedaData(
                tipo: 'Estudiante',
                titulo: "{$estudiante->apellidos}, {$estudiante->nombres}",
                subtitulo: "DNI {$estudiante->dni}",
                url: route('matricula.show', $estudiante->id),
                icono: 'academic-cap',
            ));
    }

    /**
     * @return Collection<int, ResultadoBusquedaData>
     */
    private function buscarApoderados(string $termino): Collection
    {
        return Apoderado::query()
            ->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            })
            ->with('estudiante')
            ->limit(self::LIMITE_POR_TIPO)
            ->get()
            ->filter(fn (Apoderado $apoderado) => $apoderado->estudiante !== null)
            ->map(fn (Apoderado $apoderado) => new ResultadoBusquedaData(
                tipo: 'Apoderado',
                titulo: $apoderado->nombres,
                subtitulo: "Apoderado de {$apoderado->estudiante->nombres} {$apoderado->estudiante->apellidos}",
                url: route('matricula.show', $apoderado->estudiante_id),
                icono: 'user-group',
            ));
    }

    /**
     * @return Collection<int, ResultadoBusquedaData>
     */
    private function buscarDocentes(string $termino): Collection
    {
        return Docente::query()
            ->whereHas('usuario', function ($query) use ($termino) {
                $query->where('name', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            })
            ->with('usuario')
            ->limit(self::LIMITE_POR_TIPO)
            ->get()
            ->map(fn (Docente $docente) => new ResultadoBusquedaData(
                tipo: 'Docente',
                titulo: $docente->usuario->name,
                subtitulo: $docente->especialidad ?? 'Docente',
                url: route('docentes.index', ['q' => $docente->usuario->dni]),
                icono: 'user',
            ));
    }

    /**
     * @return Collection<int, ResultadoBusquedaData>
     */
    private function buscarPersonal(string $termino): Collection
    {
        return Personal::query()
            ->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%");
            })
            ->limit(self::LIMITE_POR_TIPO)
            ->get()
            ->map(fn (Personal $personal) => new ResultadoBusquedaData(
                tipo: 'Personal',
                titulo: "{$personal->nombres} {$personal->apellidos}",
                subtitulo: $personal->cargo ?? 'Personal',
                url: route('personal.index', ['q' => $personal->dni]),
                icono: 'briefcase',
            ));
    }
}
