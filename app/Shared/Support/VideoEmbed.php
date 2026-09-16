<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Convierte el enlace que un docente pega para una clase grabada (URL de
 * YouTube, Vimeo, Google Drive o un archivo de video directo) en algo
 * reproducible dentro del Aula Virtual, en vez de solo un enlace que abre
 * una pestaña nueva. Un enlace que no calza con ningún patrón conocido
 * (o una plataforma que exige sesión, como Zoom/Teams) no es incrustable:
 * la vista debe caer de vuelta al enlace externo de siempre.
 */
final class VideoEmbed
{
    /**
     * @return array{tipo: 'iframe'|'video', src: string}|null
     */
    public static function incrustable(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{6,})/', $url, $coincidencia) === 1) {
            return ['tipo' => 'iframe', 'src' => "https://www.youtube.com/embed/{$coincidencia[1]}"];
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $coincidencia) === 1) {
            return ['tipo' => 'iframe', 'src' => "https://player.vimeo.com/video/{$coincidencia[1]}"];
        }

        if (preg_match('#drive\.google\.com/file/d/([\w-]+)#', $url, $coincidencia) === 1) {
            return ['tipo' => 'iframe', 'src' => "https://drive.google.com/file/d/{$coincidencia[1]}/preview"];
        }

        if (preg_match('/\.(mp4|webm|ogg)(?:\?.*)?$/i', $url) === 1) {
            return ['tipo' => 'video', 'src' => $url];
        }

        return null;
    }
}
