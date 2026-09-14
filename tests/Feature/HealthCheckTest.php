<?php

namespace Tests\Feature;

use App\Listeners\DiagnosticarSaludDelSistemaListener;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_responde_200_cuando_todo_esta_bien(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_el_listener_no_lanza_excepciones_cuando_todo_esta_bien(): void
    {
        $this->expectNotToPerformAssertions();

        (new DiagnosticarSaludDelSistemaListener)->handle(new DiagnosingHealth);
    }

    public function test_el_listener_lanza_una_excepcion_si_la_base_de_datos_falla(): void
    {
        DB::shouldReceive('connection->getPdo')->andThrow(new RuntimeException('sin conexión'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo conectar a la base de datos.');

        (new DiagnosticarSaludDelSistemaListener)->handle(new DiagnosingHealth);
    }

    public function test_up_responde_500_cuando_la_base_de_datos_falla(): void
    {
        config(['app.debug' => false]);
        DB::shouldReceive('connection->getPdo')->andThrow(new RuntimeException('sin conexión'));

        $this->get('/up')->assertServerError();
    }
}
