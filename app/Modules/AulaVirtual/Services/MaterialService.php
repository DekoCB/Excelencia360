<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Material;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    public function crear(CursoVirtual $curso, TipoMaterialEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?int $semana = null): Material
    {
        $this->validarDatos($tipo, $url, $archivo);

        $material = $curso->materiales()->create([
            'semana' => $semana,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'url' => $tipo->requiereArchivo() ? null : $url,
            'orden' => $curso->materiales()->count(),
        ]);

        if ($archivo) {
            // preservingOriginal() porque el mismo $archivo puede reutilizarse
            // en varias llamadas seguidas desde crearParaVarios(): sin esto,
            // Spatie MediaLibrary mueve (borra) el archivo original en la
            // primera llamada y las siguientes fallarían.
            $material->addMedia($archivo)->preservingOriginal()->toMediaCollection('archivo');
        }

        return $material;
    }

    /**
     * Crea el mismo material en varios cursos virtuales a la vez (ej. un
     * docente que dicta la misma materia en distintas aulas/horarios),
     * reutilizando el mismo archivo/URL en cada uno.
     *
     * @param  Collection<int, CursoVirtual>  $cursos
     * @return Collection<int, Material>
     */
    public function crearParaVarios(Collection $cursos, TipoMaterialEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?int $semana = null): Collection
    {
        $this->validarDatos($tipo, $url, $archivo);

        return $cursos->map(fn (CursoVirtual $curso) => $this->crear($curso, $tipo, $titulo, $url, $archivo, $semana));
    }

    public function eliminar(Material $material): void
    {
        $material->delete();
    }

    private function validarDatos(TipoMaterialEnum $tipo, ?string $url, ?UploadedFile $archivo): void
    {
        if ($tipo->requiereArchivo() && ! $archivo) {
            throw ValidationException::withMessages([
                'archivo' => "Un material de tipo «{$tipo->label()}» necesita un archivo.",
            ]);
        }

        if (! $tipo->requiereArchivo() && ! $url) {
            throw ValidationException::withMessages([
                'url' => "Un material de tipo «{$tipo->label()}» necesita una URL.",
            ]);
        }
    }
}
