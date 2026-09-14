<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Services;

use App\Modules\Docentes\Models\Contrato;
use App\Modules\Docentes\Models\Docente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ContratoService
{
    public function listar(?string $termino = null, int $perPage = 15): LengthAwarePaginator
    {
        return Contrato::query()
            ->with('docente.usuario')
            ->when($termino, fn ($query) => $query->whereHas(
                'docente.usuario',
                fn ($q) => $q->where('name', 'like', "%{$termino}%")->orWhere('dni', 'like', "%{$termino}%")
            ))
            ->latest('fecha_inicio')
            ->paginate($perPage);
    }

    /**
     * @param  array{docenteId: int, tipo: string, fechaInicio: string, fechaFin: ?string, monto: ?float, observaciones: ?string}  $datos
     */
    public function registrar(array $datos, ?UploadedFile $documento, ?int $registradoPor): Contrato
    {
        /** @var Contrato $contrato */
        $contrato = Contrato::query()->create([
            'docente_id' => $datos['docenteId'],
            'tipo' => $datos['tipo'],
            'fecha_inicio' => $datos['fechaInicio'],
            'fecha_fin' => $datos['fechaFin'],
            'monto' => $datos['monto'],
            'observaciones' => $datos['observaciones'],
            'registrado_por' => $registradoPor,
        ]);

        if ($documento) {
            $contrato->addMedia($documento)->toMediaCollection('documento');
        }

        return $contrato;
    }

    /**
     * @param  array{tipo: string, fechaInicio: string, fechaFin: ?string, monto: ?float, observaciones: ?string}  $datos
     */
    public function actualizar(Contrato $contrato, array $datos, ?UploadedFile $documento): Contrato
    {
        $contrato->update([
            'tipo' => $datos['tipo'],
            'fecha_inicio' => $datos['fechaInicio'],
            'fecha_fin' => $datos['fechaFin'],
            'monto' => $datos['monto'],
            'observaciones' => $datos['observaciones'],
        ]);

        if ($documento) {
            $contrato->addMedia($documento)->toMediaCollection('documento');
        }

        return $contrato->fresh();
    }

    public function eliminar(Contrato $contrato): void
    {
        $contrato->delete();
    }

    /**
     * @return Collection<int, Docente>
     */
    public function docentesDisponibles(): Collection
    {
        return Docente::query()->with('usuario')->get()->sortBy(fn (Docente $docente) => $docente->usuario->name)->values();
    }
}
