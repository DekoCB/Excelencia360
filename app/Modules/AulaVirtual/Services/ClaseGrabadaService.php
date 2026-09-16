<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Models\ClaseGrabada;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Seccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClaseGrabadaService
{
    public function __construct(private readonly SeccionService $secciones) {}

    public function crear(CursoVirtual $curso, TipoClaseGrabadaEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?int $seccionId = null): ClaseGrabada
    {
        $this->validarDatos($tipo, $url, $archivo);

        $claseGrabada = $curso->clasesGrabadas()->create([
            'seccion_id' => $seccionId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'url' => $tipo->requiereArchivo() ? null : $url,
            'orden' => $curso->clasesGrabadas()->count(),
        ]);

        if ($archivo) {
            // preservingOriginal() porque el mismo $archivo puede reutilizarse
            // en varias llamadas seguidas desde crearParaVarios(): sin esto,
            // Spatie MediaLibrary mueve (borra) el archivo original en la
            // primera llamada y las siguientes fallarían.
            $claseGrabada->addMedia($archivo)->preservingOriginal()->toMediaCollection('video');
        }

        return $claseGrabada;
    }

    /**
     * Crea la misma clase grabada en varios cursos virtuales a la vez (ej.
     * un docente que dicta la misma materia en distintas aulas/horarios),
     * reutilizando el mismo archivo/URL en cada uno.
     *
     * @param  Collection<int, CursoVirtual>  $cursos
     * @return Collection<int, ClaseGrabada>
     */
    public function crearParaVarios(Collection $cursos, TipoClaseGrabadaEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo, ?Seccion $seccion = null): Collection
    {
        $this->validarDatos($tipo, $url, $archivo);

        return $cursos->map(function (CursoVirtual $curso) use ($tipo, $titulo, $url, $archivo, $seccion) {
            $seccionEquivalente = $this->secciones->obtenerOCrearEquivalente($curso, $seccion);

            return $this->crear($curso, $tipo, $titulo, $url, $archivo, $seccionEquivalente?->id);
        });
    }

    public function eliminar(ClaseGrabada $claseGrabada): void
    {
        $claseGrabada->delete();
    }

    private function validarDatos(TipoClaseGrabadaEnum $tipo, ?string $url, ?UploadedFile $archivo): void
    {
        if ($tipo->requiereArchivo() && ! $archivo) {
            throw ValidationException::withMessages([
                'archivo' => "Una clase grabada de tipo «{$tipo->label()}» necesita un archivo.",
            ]);
        }

        if (! $tipo->requiereArchivo() && ! $url) {
            throw ValidationException::withMessages([
                'url' => "Una clase grabada de tipo «{$tipo->label()}» necesita una URL.",
            ]);
        }
    }
}
