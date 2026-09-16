<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboService
{
    /**
     * Tamaño de papel para impresoras térmicas de recibos (80mm de ancho).
     * DomPDF no soporta "alto indeterminado" como un rollo térmico real, así
     * que se fija un alto generoso (200mm) que nunca llena un recibo de una
     * sola línea de concepto -- la impresora corta donde termina el
     * contenido, igual que con cualquier otro PDF de una página.
     *
     * @var array{0: int, 1: int, 2: float, 3: float}
     */
    private const PAPEL_80MM = [0, 0, 226.77, 566.93];

    /**
     * Cada recibo se emite en UNA sola serie, elegida a mano por quien
     * aprueba el pago -- como si hubiera dos talonarios físicos en
     * circulación (001 y 002) y se decidiera de cuál sacar el número esta
     * vez. Por eso cada serie lleva su propio correlativo independiente,
     * no uno compartido.
     */
    public function emitir(Pago $pago, SerieReciboEnum $serie): Recibo
    {
        $numeroRecibo = $this->siguienteNumero($serie);

        /** @var Recibo $recibo */
        $recibo = Recibo::query()->create([
            'pago_id' => $pago->id,
            'serie' => $serie,
            'numero_recibo' => $numeroRecibo,
            'emitido_en' => now(),
        ]);

        $this->generarPdfs($recibo, $pago, $serie);

        return $recibo;
    }

    /**
     * Genera y guarda las dos versiones del PDF del recibo: A4 (para
     * archivo/impresión normal) y 80mm (para impresora térmica de punto de
     * venta) -- las mismas dos que usa RegenerarRecibos para reconstruirlas.
     */
    public function generarPdfs(Recibo $recibo, Pago $pago, SerieReciboEnum $serie): void
    {
        $pdfA4 = Pdf::loadView('pdf.recibo', ['pago' => $pago, 'recibo' => $recibo, 'serie' => $serie]);

        $recibo->addMediaFromString($pdfA4->output())
            ->usingFileName("{$serie->value}-{$recibo->numero_recibo}.pdf")
            ->toMediaCollection('pdf');

        $pdf80mm = Pdf::loadView('pdf.recibo-80mm', ['pago' => $pago, 'recibo' => $recibo, 'serie' => $serie])
            ->setPaper(self::PAPEL_80MM);

        $recibo->addMediaFromString($pdf80mm->output())
            ->usingFileName("{$serie->value}-{$recibo->numero_recibo}-80mm.pdf")
            ->toMediaCollection('pdf_80mm');
    }

    /**
     * Correlativo continuo por serie (nunca se reinicia por año): si se
     * reiniciara cada año se repetiría el número y rompería el unique()
     * de (serie, numero_recibo).
     */
    private function siguienteNumero(SerieReciboEnum $serie): string
    {
        return sprintf('%06d', Recibo::query()->where('serie', $serie)->count() + 1);
    }
}
