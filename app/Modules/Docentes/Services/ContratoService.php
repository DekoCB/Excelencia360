<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Services;

use App\Models\User;
use App\Modules\Docentes\Models\Contrato;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

/**
 * Contrato ya no es exclusivo de Docente (punto 10 del backlog): se
 * gestiona desde la ficha de cada usuario, no desde una pantalla propia
 * -- ver resources/views/components/usuarios/contratos-usuario.blade.php.
 */
class ContratoService
{
    /**
     * @return Collection<int, Contrato>
     */
    public function deUsuario(User $usuario): Collection
    {
        return Contrato::query()
            ->where('user_id', $usuario->id)
            ->latest('fecha_inicio')
            ->get();
    }

    /**
     * @param  array{tipo: string, fechaInicio: string, fechaFin: ?string, monto: ?float, observaciones: ?string}  $datos
     */
    public function registrar(User $usuario, array $datos, ?UploadedFile $documento, ?int $registradoPor): Contrato
    {
        /** @var Contrato $contrato */
        $contrato = Contrato::query()->create([
            'user_id' => $usuario->id,
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
}
