<?php

namespace Tests\Feature\Landing;

use App\Modules\Landing\Models\SolicitudContacto;
use App\Modules\Landing\Services\SolicitudContactoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SolicitudContactoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_de_contacto_guarda_la_solicitud(): void
    {
        Volt::test('landing.index')
            ->set('nombre', 'Juan Pérez')
            ->set('email', 'juan.perez@example.com')
            ->set('telefono', '987654321')
            ->set('asunto', 'Curso: Ofimática Aplicada a la Gestión Pública')
            ->set('mensaje', 'Quisiera información sobre el inicio del curso.')
            ->call('enviarMensaje')
            ->assertHasNoErrors()
            ->assertSet('enviado', true)
            ->assertSet('errorEnvio', false)
            ->assertSet('nombre', '')
            ->assertSee('Mensaje enviado');

        $this->assertDatabaseHas('solicitudes_contacto', [
            'nombre' => 'Juan Pérez',
            'email' => 'juan.perez@example.com',
            'telefono' => '987654321',
            'asunto' => 'Curso: Ofimática Aplicada a la Gestión Pública',
            'mensaje' => 'Quisiera información sobre el inicio del curso.',
        ]);
    }

    public function test_el_formulario_exige_los_campos_obligatorios(): void
    {
        Volt::test('landing.index')
            ->set('nombre', '')
            ->set('email', 'correo-invalido')
            ->set('telefono', '')
            ->set('asunto', '')
            ->set('mensaje', '')
            ->call('enviarMensaje')
            ->assertHasErrors(['nombre', 'email', 'telefono', 'asunto', 'mensaje']);

        $this->assertDatabaseCount('solicitudes_contacto', 0);
    }

    public function test_el_asunto_debe_ser_uno_de_los_cursos_servicios_o_consulta_general(): void
    {
        Volt::test('landing.index')
            ->set('nombre', 'Ana Torres')
            ->set('email', 'ana@example.com')
            ->set('telefono', '999888777')
            ->set('asunto', 'Un asunto inventado')
            ->set('mensaje', 'Consulta.')
            ->call('enviarMensaje')
            ->assertHasErrors(['asunto']);

        $this->assertDatabaseCount('solicitudes_contacto', 0);
    }

    public function test_el_asunto_llega_preseleccionado_por_la_url(): void
    {
        $this->get('/?asunto='.rawurlencode('Servicio: Consultoría'))
            ->assertOk()
            ->assertSee('Servicio: Consultoría');

        $this->get('/?asunto='.rawurlencode('Algo que no existe'))
            ->assertOk()
            ->assertDontSee('Algo que no existe');
    }

    public function test_si_falla_el_guardado_el_formulario_muestra_el_error_y_conserva_los_datos(): void
    {
        $servicio = Mockery::mock(SolicitudContactoService::class);
        $servicio->shouldReceive('registrar')->once()->andThrow(new RuntimeException('BD caída'));
        $this->app->instance(SolicitudContactoService::class, $servicio);

        Volt::test('landing.index')
            ->set('nombre', 'Ana Torres')
            ->set('email', 'ana@example.com')
            ->set('telefono', '999888777')
            ->set('asunto', 'Consulta general')
            ->set('mensaje', 'Consulta.')
            ->call('enviarMensaje')
            ->assertHasNoErrors()
            ->assertSet('errorEnvio', true)
            ->assertSet('enviado', false)
            ->assertSet('nombre', 'Ana Torres')
            ->assertSee('No pudimos enviar tu mensaje');
    }

    public function test_registrar_crea_la_solicitud_en_base_de_datos(): void
    {
        $service = $this->app->make(SolicitudContactoService::class);

        $solicitud = $service->registrar('Ana Torres', 'ana@example.com', '999888777', null, 'Consulta general.');

        $this->assertInstanceOf(SolicitudContacto::class, $solicitud);
        $this->assertFalse($solicitud->refresh()->atendido);
        $this->assertDatabaseHas('solicitudes_contacto', ['nombre' => 'Ana Torres', 'atendido' => false]);
    }
}
