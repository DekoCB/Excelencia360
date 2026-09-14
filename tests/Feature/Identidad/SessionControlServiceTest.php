<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Models\RegistroIngreso;
use App\Modules\Identidad\Services\SessionControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionControlServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SessionControlService
    {
        return $this->app->make(SessionControlService::class);
    }

    private function crearFilaDeSesion(string $sesionId, User $usuario): void
    {
        DB::table('sessions')->insert([
            'id' => $sesionId,
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('datos'),
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_registrar_ingreso_crea_una_fila_abierta(): void
    {
        $usuario = User::factory()->create();

        $registro = $this->service()->registrarIngreso($usuario, 'Fiorella Vargas', 'sesion-1', '127.0.0.1');

        $this->assertSame($usuario->id, $registro->user_id);
        $this->assertSame('Fiorella Vargas', $registro->nombre);
        $this->assertNotNull($registro->iniciado_en);
        $this->assertNull($registro->finalizado_en);
    }

    public function test_finalizar_ingreso_cierra_la_fila_abierta_de_esa_sesion(): void
    {
        $usuario = User::factory()->create();
        $this->service()->registrarIngreso($usuario, 'Fiorella Vargas', 'sesion-1', '127.0.0.1');

        $this->service()->finalizarIngreso('sesion-1');

        $this->assertNotNull(RegistroIngreso::query()->where('session_id', 'sesion-1')->first()->finalizado_en);
    }

    public function test_finalizar_ingreso_de_una_sesion_sin_registro_no_falla(): void
    {
        $this->service()->finalizarIngreso('sesion-inexistente');

        $this->assertSame(0, RegistroIngreso::query()->count());
    }

    public function test_sesiones_de_incluye_el_nombre_registrado(): void
    {
        $usuario = User::factory()->create();
        $this->crearFilaDeSesion('sesion-1', $usuario);
        $this->service()->registrarIngreso($usuario, 'Fiorella Vargas', 'sesion-1', '127.0.0.1');

        $sesiones = $this->service()->sesionesDe($usuario, 'sesion-1');

        $this->assertSame('Fiorella Vargas', $sesiones->first()->nombre);
    }

    public function test_sesiones_de_sin_registro_deja_el_nombre_nulo(): void
    {
        $usuario = User::factory()->create();
        $this->crearFilaDeSesion('sesion-1', $usuario);

        $sesiones = $this->service()->sesionesDe($usuario, 'sesion-1');

        $this->assertNull($sesiones->first()->nombre);
    }

    public function test_revocar_finaliza_el_ingreso_antes_de_borrar_la_sesion(): void
    {
        $usuario = User::factory()->create();
        $this->crearFilaDeSesion('sesion-1', $usuario);
        $this->service()->registrarIngreso($usuario, 'Fiorella Vargas', 'sesion-1', '127.0.0.1');

        $this->service()->revocar('sesion-1');

        $this->assertNotNull(RegistroIngreso::query()->where('session_id', 'sesion-1')->first()->finalizado_en);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-1']);
    }

    public function test_horas_por_nombre_solo_suma_ingresos_ya_cerrados(): void
    {
        $usuario = User::factory()->create();

        RegistroIngreso::query()->create([
            'user_id' => $usuario->id,
            'session_id' => 'sesion-1',
            'nombre' => 'Fiorella Vargas',
            'iniciado_en' => now()->subHours(3),
            'finalizado_en' => now()->subHours(1),
        ]);

        RegistroIngreso::query()->create([
            'user_id' => $usuario->id,
            'session_id' => 'sesion-2',
            'nombre' => 'Fiorella Vargas',
            'iniciado_en' => now()->subMinutes(30),
            'finalizado_en' => null,
        ]);

        $horas = $this->service()->horasPorNombre($usuario);

        $this->assertCount(1, $horas);
        $this->assertSame('Fiorella Vargas', $horas->first()['nombre']);
        $this->assertSame(2.0, $horas->first()['horas']);
        $this->assertSame(1, $horas->first()['ingresos']);
    }

    public function test_horas_por_nombre_agrupa_distintos_nombres_por_separado(): void
    {
        $usuario = User::factory()->create();

        RegistroIngreso::query()->create([
            'user_id' => $usuario->id,
            'session_id' => 'sesion-1',
            'nombre' => 'Ana',
            'iniciado_en' => now()->subHours(2),
            'finalizado_en' => now()->subHours(1),
        ]);

        RegistroIngreso::query()->create([
            'user_id' => $usuario->id,
            'session_id' => 'sesion-2',
            'nombre' => 'Beto',
            'iniciado_en' => now()->subHours(4),
            'finalizado_en' => now()->subHours(1),
        ]);

        $horas = $this->service()->horasPorNombre($usuario);

        $this->assertCount(2, $horas);
    }
}
