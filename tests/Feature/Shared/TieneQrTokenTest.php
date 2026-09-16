<?php

namespace Tests\Feature\Shared;

use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TieneQrTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_genera_un_token_la_primera_vez_y_lo_persiste(): void
    {
        $estudiante = Estudiante::factory()->create();
        $this->assertNull($estudiante->qr_token);

        $token = $estudiante->obtenerOCrearQrToken();

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('estudiantes', ['id' => $estudiante->id, 'qr_token' => $token]);
    }

    public function test_devuelve_siempre_el_mismo_token_una_vez_generado(): void
    {
        $estudiante = Estudiante::factory()->create();

        $primero = $estudiante->obtenerOCrearQrToken();
        $segundo = $estudiante->fresh()->obtenerOCrearQrToken();

        $this->assertSame($primero, $segundo);
    }

    public function test_dos_estudiantes_reciben_tokens_distintos(): void
    {
        $uno = Estudiante::factory()->create()->obtenerOCrearQrToken();
        $otro = Estudiante::factory()->create()->obtenerOCrearQrToken();

        $this->assertNotSame($uno, $otro);
    }

    public function test_funciona_igual_para_un_docente(): void
    {
        $docente = Docente::factory()->create();
        $this->assertNull($docente->qr_token);

        $token = $docente->obtenerOCrearQrToken();

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('docentes', ['id' => $docente->id, 'qr_token' => $token]);
    }
}
