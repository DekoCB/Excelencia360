<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_publica_carga_sin_autenticarse_con_la_identidad_de_excelencia_360(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('EXCELENCIA 360 | Formación y Capacitación')
            ->assertSee('Educación que')
            ->assertSee('Explorar cursos')
            ->assertSee('GRUPO EXCELENCIA 360')
            ->assertSee('Nuestros valores')
            ->assertSee('Nuestros cursos')
            ->assertSee('Nuestros servicios')
            ->assertSee('Contáctanos')
            ->assertSee(config('institucion.ruc'))
            ->assertSee('Todos los derechos reservados');
    }

    public function test_la_pagina_publica_muestra_los_datos_institucionales_sin_rastros_de_ceba(): void
    {
        $respuesta = $this->get('/')->assertOk();

        foreach (config('institucion.cursos') as $curso) {
            $respuesta->assertSee($curso['nombre']);
        }

        foreach (config('institucion.servicios') as $servicio) {
            $respuesta->assertSee($servicio['nombre']);
        }

        foreach (config('institucion.valores') as $valor) {
            $respuesta->assertSee($valor['nombre']);
        }

        $respuesta
            ->assertSee(config('institucion.mision'))
            ->assertSee(config('institucion.vision'))
            ->assertSee(config('institucion.gerente_general'))
            ->assertDontSee('CEBA')
            ->assertDontSee('MINEDU');
    }

    public function test_el_blog_sin_publicaciones_muestra_un_estado_vacio_en_vez_de_contenido_ficticio(): void
    {
        config()->set('institucion.blog', []);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pronto publicaremos nuestras primeras entradas');
    }

    public function test_el_blog_con_publicaciones_las_lista(): void
    {
        config()->set('institucion.blog', [[
            'titulo' => 'Cómo organizar tu tiempo para estudiar en línea',
            'extracto' => 'Consejos para aprovechar la modalidad virtual.',
            'categoria' => 'Aprendizaje virtual',
            'fecha' => '2026-09-14',
            'autor' => 'Equipo Excelencia 360',
            'url' => '/blog/organizar-tu-tiempo',
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Cómo organizar tu tiempo para estudiar en línea')
            ->assertSee('Leer más')
            ->assertDontSee('Pronto publicaremos');
    }

    public function test_la_pagina_de_un_curso_carga_con_su_titulo_y_su_ficha(): void
    {
        $curso = config('institucion.cursos')[0];

        $this->get(route('landing.curso', $curso['slug']))
            ->assertOk()
            ->assertSee('<title>'.$curso['nombre'].' | '.config('institucion.nombre').'</title>', false)
            ->assertSee($curso['descripcion'])
            ->assertSee('Ficha del curso')
            ->assertSee('Solicitar información')
            ->assertSee('Otros cursos');
    }

    public function test_un_curso_inexistente_devuelve_404(): void
    {
        $this->get('/cursos/curso-que-no-existe')->assertNotFound();
    }

    public function test_el_boton_de_iniciar_sesion_sigue_llevando_al_login(): void
    {
        $this->get('/')->assertSee(route('login'), false);
    }

    public function test_el_menu_incluye_validacion_de_certificados_entre_blog_y_contactanos(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $posBlog = mb_strpos($html, '>Blog<');
        $posValidacion = mb_strpos($html, '>Validación de Certificados<');
        $posContacto = mb_strpos($html, '>Contáctanos<');

        $this->assertNotFalse($posBlog);
        $this->assertNotFalse($posValidacion);
        $this->assertNotFalse($posContacto);
        $this->assertTrue($posBlog < $posValidacion && $posValidacion < $posContacto);

        $this->get('/')->assertSee(route('certificados.verificar'), false);
    }

    public function test_dashboard_sigue_exigiendo_autenticacion(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
