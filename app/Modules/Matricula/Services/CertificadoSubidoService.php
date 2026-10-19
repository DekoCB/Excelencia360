<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Modules\Matricula\Enums\TipoCertificadoSubidoEnum;
use App\Modules\Matricula\Models\CertificadoSubido;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CertificadoSubidoService
{
    public function subir(Estudiante $estudiante, TipoCertificadoSubidoEnum $tipo, UploadedFile $archivo, ?int $cursoId, ?int $subidoPor): CertificadoSubido
    {
        if ($tipo === TipoCertificadoSubidoEnum::CAPACITACION && $cursoId === null) {
            throw ValidationException::withMessages([
                'curso_id' => 'Elige el curso de capacitación al que corresponde este certificado.',
            ]);
        }

        $certificado = $estudiante->certificadosSubidos()->create([
            'tipo' => $tipo,
            'curso_id' => $tipo === TipoCertificadoSubidoEnum::CAPACITACION ? $cursoId : null,
            'subido_por' => $subidoPor,
        ]);

        $certificado->addMedia($archivo)
            ->usingFileName($tipo->value.'-'.$estudiante->id.'-'.$certificado->id.'.'.$archivo->getClientOriginalExtension())
            ->toMediaCollection('archivo');

        return $certificado;
    }

    public function eliminar(CertificadoSubido $certificado): void
    {
        $certificado->delete();
    }
}
