<?php

namespace Tests\Unit\Shared;

use App\Shared\Support\QrCode;
use PHPUnit\Framework\TestCase;

class QrCodeTest extends TestCase
{
    /**
     * La decodificación real del contenido (que efectivamente sea un QR
     * leíble, no solo una imagen con esa forma) se verificó a mano con un
     * lector independiente sobre un PDF real generado por el sistema —
     * bacon/bacon-qr-code no trae un decodificador en PHP para probarlo
     * aquí. Este test cubre lo que sí se puede automatizar: que el data
     * URI es un PNG válido y que es determinista.
     */
    public function test_devuelve_un_data_uri_png_valido(): void
    {
        $dataUri = QrCode::pngBase64('https://excelencia360.test/verificar-certificado?codigo=ABCD1234EF');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $binario = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binario);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $binario);

        $imagen = imagecreatefromstring($binario);
        $this->assertNotFalse($imagen);
    }

    public function test_el_mismo_contenido_genera_siempre_la_misma_imagen(): void
    {
        $uno = QrCode::pngBase64('mismo-contenido');
        $dos = QrCode::pngBase64('mismo-contenido');

        $this->assertSame($uno, $dos);
    }

    public function test_contenidos_distintos_generan_imagenes_distintas(): void
    {
        $uno = QrCode::pngBase64('contenido-uno');
        $dos = QrCode::pngBase64('contenido-otro');

        $this->assertNotSame($uno, $dos);
    }

    public function test_respeta_aproximadamente_el_tamano_pedido(): void
    {
        $dataUri = QrCode::pngBase64('contenido', tamanoPx: 300);
        $binario = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $imagen = imagecreatefromstring($binario);

        // El lado final es un múltiplo entero del tamaño de módulo (para
        // que cada módulo del QR quede en píxeles enteros, sin bordes
        // borrosos), así que puede quedar unos píxeles por debajo o por
        // encima del pedido -- nunca lejos.
        $this->assertGreaterThan(250, imagesx($imagen));
        $this->assertLessThan(350, imagesx($imagen));
        $this->assertSame(imagesx($imagen), imagesy($imagen));
    }
}
