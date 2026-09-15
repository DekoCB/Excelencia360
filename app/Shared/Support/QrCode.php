<?php

declare(strict_types=1);

namespace App\Shared\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;

/**
 * Códigos QR embebibles en los PDFs (data URI). Dibuja la matriz de
 * módulos de bacon/bacon-qr-code a mano con GD, en vez de usar sus
 * renderers (Svg/Imagick): DomPDF no soporta SVG de forma confiable y
 * este servidor no tiene la extensión Imagick, solo GD (ya usado por
 * Media Library) -- ver `php -m`.
 */
final class QrCode
{
    /**
     * @return string Data URI "data:image/png;base64,..." lista para un <img>.
     */
    public static function pngBase64(string $contenido, int $tamanoPx = 240, int $margenModulos = 2): string
    {
        $matriz = Encoder::encode($contenido, ErrorCorrectionLevel::M())->getMatrix();
        $binario = self::dibujarPng($matriz, $tamanoPx, $margenModulos);

        return 'data:image/png;base64,'.base64_encode($binario);
    }

    private static function dibujarPng(ByteMatrix $matriz, int $tamanoPx, int $margenModulos): string
    {
        $modulos = $matriz->getWidth();
        $totalModulos = $modulos + ($margenModulos * 2);
        // Escala entera: un módulo desalineado por redondeo se nota mucho
        // más en un código QR (deja de ser cuadrado y puede volverse
        // ilegible) que un par de píxeles de diferencia en el tamaño final.
        $escala = max((int) round($tamanoPx / $totalModulos), 1);
        $lado = $escala * $totalModulos;

        $imagen = imagecreatetruecolor($lado, $lado);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $negro = imagecolorallocate($imagen, 0, 0, 0);
        imagefilledrectangle($imagen, 0, 0, $lado - 1, $lado - 1, $blanco);

        for ($y = 0; $y < $modulos; $y++) {
            for ($x = 0; $x < $modulos; $x++) {
                if ($matriz->get($x, $y) === 1) {
                    $px = ($x + $margenModulos) * $escala;
                    $py = ($y + $margenModulos) * $escala;
                    imagefilledrectangle($imagen, $px, $py, $px + $escala - 1, $py + $escala - 1, $negro);
                }
            }
        }

        ob_start();
        imagepng($imagen);
        $binario = (string) ob_get_clean();
        imagedestroy($imagen);

        return $binario;
    }
}
