<?php

namespace Tests\Unit\Shared;

use App\Shared\Support\VideoEmbed;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public function test_reconoce_un_enlace_normal_de_youtube(): void
    {
        $resultado = VideoEmbed::incrustable('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame(['tipo' => 'iframe', 'src' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'], $resultado);
    }

    public function test_reconoce_un_enlace_corto_de_youtube(): void
    {
        $resultado = VideoEmbed::incrustable('https://youtu.be/dQw4w9WgXcQ?si=abc');

        $this->assertSame(['tipo' => 'iframe', 'src' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'], $resultado);
    }

    public function test_reconoce_vimeo(): void
    {
        $resultado = VideoEmbed::incrustable('https://vimeo.com/76979871');

        $this->assertSame(['tipo' => 'iframe', 'src' => 'https://player.vimeo.com/video/76979871'], $resultado);
    }

    public function test_reconoce_google_drive(): void
    {
        $resultado = VideoEmbed::incrustable('https://drive.google.com/file/d/1AbCdEfGhIjK/view?usp=sharing');

        $this->assertSame(['tipo' => 'iframe', 'src' => 'https://drive.google.com/file/d/1AbCdEfGhIjK/preview'], $resultado);
    }

    public function test_reconoce_un_archivo_de_video_directo(): void
    {
        $resultado = VideoEmbed::incrustable('https://cdn.excelencia360.test/clases/2026-09-15.mp4');

        $this->assertSame(['tipo' => 'video', 'src' => 'https://cdn.excelencia360.test/clases/2026-09-15.mp4'], $resultado);
    }

    public function test_un_enlace_no_reconocido_no_es_incrustable(): void
    {
        $this->assertNull(VideoEmbed::incrustable('https://zoom.us/rec/share/algo'));
    }

    public function test_un_enlace_vacio_no_es_incrustable(): void
    {
        $this->assertNull(VideoEmbed::incrustable(null));
        $this->assertNull(VideoEmbed::incrustable('  '));
    }
}
