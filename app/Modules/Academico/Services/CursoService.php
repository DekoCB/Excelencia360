<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\TipoCursoEnum;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Repositories\Contracts\CursoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CursoService
{
    public function __construct(
        private readonly CursoRepositoryInterface $cursos,
    ) {}

    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->cursos->paginate($perPage);
    }

    /**
     * Mismo listado que listar(), pero filtrado por tipo -- se consulta
     * directo (sin pasar por el repositorio genérico, que es compartido
     * por todos los módulos y no conoce este filtro) para la pantalla de
     * Cursos, que ahora muestra académicos y de capacitación en una sola
     * tabla con un selector de tipo.
     */
    public function listarPorTipo(TipoCursoEnum $tipo, int $perPage = 15): LengthAwarePaginator
    {
        return Curso::query()->with('grados')->where('tipo', $tipo)->paginate($perPage);
    }

    /**
     * Catálogo de cursos de capacitación, para el selector de "Emitir
     * certificado" -- reemplaza a la antigua CursoCapacitacionService::todos().
     *
     * @return Collection<int, Curso>
     */
    public function deCapacitacion(): Collection
    {
        return Curso::query()->where('tipo', TipoCursoEnum::CAPACITACION)->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string, codigo: string, grado_ids: list<int>, franjas_permitidas: ?list<string>, horas: int}  $datos
     */
    public function crear(array $datos): Curso
    {
        $gradoIds = $datos['grado_ids'];
        unset($datos['grado_ids']);

        $curso = $this->cursos->create($datos);
        $curso->grados()->sync($gradoIds);

        return $curso;
    }

    /**
     * @param  array{nombre: string, codigo: string, grado_ids: list<int>, franjas_permitidas: ?list<string>, horas: int, activo: bool}  $datos
     */
    public function actualizar(Curso $curso, array $datos): Curso
    {
        $gradoIds = $datos['grado_ids'];
        unset($datos['grado_ids']);

        $curso = $this->cursos->update($curso, $datos);
        $curso->grados()->sync($gradoIds);

        return $curso;
    }

    public function codigoDisponible(string $codigo, ?int $exceptoId = null): bool
    {
        return ! $this->cursos->existeCodigo($codigo, $exceptoId);
    }

    /**
     * Código sugerido para un curso nuevo: las tres primeras iniciales del
     * nombre (sin tildes) más el número de grado, p. ej. "Comunicación" en
     * Grado 1 → "COM-1". Si ya existe (dos grados distintos pueden
     * compartir el mismo orden, como "Mayores"/"Menores"), se agrega un
     * sufijo numérico hasta encontrar uno libre.
     */
    public function generarCodigo(string $nombre, Grado $grado): string
    {
        $base = $this->iniciales($nombre).'-'.$grado->orden;

        if ($this->codigoDisponible($base)) {
            return $base;
        }

        $sufijo = 2;

        while (! $this->codigoDisponible($codigo = "{$base}-{$sufijo}")) {
            $sufijo++;
        }

        return $codigo;
    }

    /**
     * Mismo criterio que generarCodigo(), pero para un curso de
     * capacitación: no tiene grado, así que el código se basa solo en un
     * prefijo fijo + las iniciales del nombre (p. ej. "Ofimática" → "CAP-OFI").
     */
    public function generarCodigoCapacitacion(string $nombre): string
    {
        $base = 'CAP-'.$this->iniciales($nombre);

        if ($this->codigoDisponible($base)) {
            return $base;
        }

        $sufijo = 2;

        while (! $this->codigoDisponible($codigo = "{$base}-{$sufijo}")) {
            $sufijo++;
        }

        return $codigo;
    }

    private function iniciales(string $nombre): string
    {
        $sinTildes = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'],
            $nombre,
        );

        $soloLetras = preg_replace('/[^A-Za-z]/', '', $sinTildes) ?? '';

        return mb_strtoupper(mb_substr($soloLetras, 0, 3));
    }
}
