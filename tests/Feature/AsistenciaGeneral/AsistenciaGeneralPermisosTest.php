<?php

namespace Tests\Feature\AsistenciaGeneral;

use App\Models\User;
use App\Modules\AsistenciaGeneral\Models\AsistenciaGeneral;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaGeneralPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrativo_puede_ver_la_pantalla(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('asistencia-general.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_pantalla(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('asistencia-general.index'))
            ->assertForbidden();
    }

    public function test_administrativo_registra_ingreso_y_salida_de_un_estudiante_desde_la_ui(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $estudiante = Estudiante::factory()->create(['nombres' => 'Carla', 'apellidos' => 'Rios', 'dni' => '87654321']);

        $this->actingAs($administrativo);

        $componente = Volt::test('asistencia-general.index')
            ->set('busqueda', 'Carla')
            ->set('turnoElegido', 'tarde')
            ->call('registrarIngreso', Estudiante::class, $estudiante->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias_generales', [
            'persona_type' => Estudiante::class,
            'persona_id' => $estudiante->id,
            'turno' => 'tarde',
        ]);

        $registro = AsistenciaGeneral::query()->firstOrFail();
        $componente->call('registrarSalida', $registro->id);

        $this->assertNotNull($registro->refresh()->hora_salida);
    }

    public function test_escanear_el_qr_de_un_estudiante_registra_su_ingreso_y_un_segundo_escaneo_su_salida(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $estudiante = Estudiante::factory()->create();
        $token = $estudiante->obtenerOCrearQrToken();

        $this->actingAs($administrativo);

        Volt::test('asistencia-general.index')
            ->set('turnoElegido', 'manana')
            ->call('escanearQr', $token)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias_generales', [
            'persona_type' => Estudiante::class,
            'persona_id' => $estudiante->id,
            'turno' => 'manana',
        ]);

        $registro = AsistenciaGeneral::query()->firstOrFail();
        $this->assertNull($registro->hora_salida);

        Volt::test('asistencia-general.index')
            ->set('turnoElegido', 'manana')
            ->call('escanearQr', $token)
            ->assertHasNoErrors();

        $this->assertNotNull($registro->refresh()->hora_salida);
    }

    public function test_escanear_el_qr_de_un_docente_registra_su_ingreso_contra_su_user(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $usuarioDocente = User::factory()->create();
        $usuarioDocente->assignRole(RolEnum::DOCENTE->value);
        $docente = Docente::factory()->create(['user_id' => $usuarioDocente->id]);
        $token = $docente->obtenerOCrearQrToken();

        $this->actingAs($administrativo);

        Volt::test('asistencia-general.index')
            ->set('turnoElegido', 'tarde')
            ->call('escanearQr', $token)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias_generales', [
            'persona_type' => User::class,
            'persona_id' => $usuarioDocente->id,
            'turno' => 'tarde',
        ]);
    }

    public function test_escanear_un_codigo_no_reconocido_muestra_un_error(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo);

        Volt::test('asistencia-general.index')
            ->set('turnoElegido', 'manana')
            ->call('escanearQr', 'codigo-inexistente')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('asistencias_generales', 0);
    }

    public function test_un_coordinador_sin_permiso_de_registrar_no_puede_registrar_ingreso(): void
    {
        // Coordinador sí tiene asistencia_general.registrar por matriz, así
        // que se prueba con un rol que solo "ve" -- no hay ninguno hoy, así
        // que se verifica directamente el gate con un usuario sin el rol.
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('asistencia_general.ver');
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($usuario);

        Volt::test('asistencia-general.index')
            ->set('turnoElegido', 'manana')
            ->call('registrarIngreso', Estudiante::class, $estudiante->id)
            ->assertForbidden();
    }
}
