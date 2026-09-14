<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\UploadedFile;

class DocumentoEstudianteService
{
    public function subir(Estudiante $estudiante, TipoDocumentoEnum $tipo, UploadedFile $archivo, ?int $subidoPor, ?UploadedFile $reverso = null): DocumentoEstudiante
    {
        /** @var DocumentoEstudiante $documento */
        $documento = $estudiante->documentos()->updateOrCreate(
            ['tipo' => $tipo],
            ['subido_por' => $subidoPor, 'verificado' => false],
        );

        $documento->addMedia($archivo)
            ->usingFileName($tipo->value.'-'.$estudiante->id.'.'.$archivo->getClientOriginalExtension())
            ->toMediaCollection('archivo');

        if ($reverso !== null) {
            $documento->addMedia($reverso)
                ->usingFileName($tipo->value.'-reverso-'.$estudiante->id.'.'.$reverso->getClientOriginalExtension())
                ->toMediaCollection('reverso');
        }

        return $documento;
    }

    public function verificar(DocumentoEstudiante $documento): void
    {
        $documento->update(['verificado' => true]);
    }

    /**
     * Solo tiene sentido para documentos con ambas caras subidas (ver
     * tieneAmbasCaras() del lado del componente); el caller es responsable
     * de no ofrecer este botón si falta alguna.
     */
    public function generarPdfDni(DocumentoEstudiante $documento): DomPdf
    {
        return Pdf::loadView('pdf.documento-dni', [
            'documento' => $documento,
            'caraUrl' => $documento->getFirstMediaPath('archivo'),
            'reversoUrl' => $documento->getFirstMediaPath('reverso'),
        ]);
    }
}
