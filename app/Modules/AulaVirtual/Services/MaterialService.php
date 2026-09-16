<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Material;
use App\Modules\AulaVirtual\Models\Seccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    public function __construct(private readonly SeccionService $secciones) {}

    public function crear(CursoVirtual $curso, TipoMaterialEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?int $seccionId = null): Material
    {
        $this->validarDatos($tipo, $url, $archivo);

        $material = $curso->materiales()->create([
            'seccion_id' => $seccionId,
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
     * reutilizando el mismo archivo/URL en cada uno. La sección elegida
     * pertenece a un solo curso virtual (el que se estaba viendo), así
     * que en cada curso destino se busca -- o se crea -- su equivalente
     * por nombre/fecha en vez de reutilizar el mismo seccion_id.
     *
     * @param  Collection<int, CursoVirtual>  $cursos
     * @return Collection<int, Material>
     */
    public function crearParaVarios(Collection $cursos, TipoMaterialEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?Seccion $seccion = null): Collection
    {
        $this->validarDatos($tipo, $url, $archivo);

        return $cursos->map(function (CursoVirtual $curso) use ($tipo, $titulo, $url, $archivo, $seccion) {
            $seccionEquivalente = $this->secciones->obtenerOCrearEquivalente($curso, $seccion);

            return $this->crear($curso, $tipo, $titulo, $url, $archivo, $seccionEquivalente?->id);
        });
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
