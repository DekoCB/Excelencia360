<?php

namespace Tests\Feature\AsistenciaDocentes;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Models\AsistenciaDocente;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaDocenteQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_staff_escanea_el_qr_de_un_docente_y_lo_marca_presente(): void
    {
        $docente = Docente::factory()->create();

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('asistencia-docentes.index')
            ->call('escanearQr', $docente->obtenerOCrearQrToken())
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias_docentes', [
            'docente_id' => $docente->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::PRESENTE->value,
            'registrado_por' => $coordinador->id,
        ]);
    }

    public function test_escanear_un_codigo_no_reconocido_no_crea_registro(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('asistencia-docentes.index')
            ->call('escanearQr', 'codigo-inventado-que-no-existe')
            ->assertSee('Código QR no reconocido.');

        $this->assertDatabaseCount('asistencias_docentes', 0);
    }

    public function test_escanear_no_pisa_un_registro_que_ya_existe_para_hoy(): void
    {
        $docente = Docente::factory()->create();
        AsistenciaDocente::factory()->for($docente, 'docente')->create([
            'fecha' => now()->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::TARDANZA->value,
        ]);

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('asistencia-docentes.index')
            ->call('escanearQr', $docente->obtenerOCrearQrToken());

        $this->assertDatabaseHas('asistencias_docentes', [
            'docente_id' => $docente->id,
            'estado' => EstadoAsistenciaEnum::TARDANZA->value,
        ]);
        $this->assertDatabaseCount('asistencias_docentes', 1);
    }

    public function test_un_docente_no_puede_llamar_escanearqr_directamente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);
        $otroDocente = Docente::factory()->create();

        $this->actingAs($usuario);

        Volt::test('asistencia-docentes.index')
            ->call('escanearQr', $otroDocente->obtenerOCrearQrToken())
            ->assertForbidden();

        $this->assertDatabaseCount('asistencias_docentes', 0);
    }
}
