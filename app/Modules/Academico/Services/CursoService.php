<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Repositories\Contracts\CursoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * @param  array{nombre: string, codigo: string, grado_id: int, franjas_permitidas: ?list<string>, horas: int}  $datos
     */
    public function crear(array $datos): Curso
    {
        return $this->cursos->create($datos);
    }

    /**
     * @param  array{nombre: string, codigo: string, grado_id: int, franjas_permitidas: ?list<string>, horas: int, activo: bool}  $datos
     */
    public function actualizar(Curso $curso, array $datos): Curso
    {
        return $this->cursos->update($curso, $datos);
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
