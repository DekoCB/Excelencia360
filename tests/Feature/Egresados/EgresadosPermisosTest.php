<?php

namespace Tests\Feature\Egresados;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EgresadosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_el_listado_de_egresados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario)->get(route('egresados.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_listado_de_egresados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($usuario)->get(route('egresados.index'))->assertForbidden();
    }

    public function test_el_listado_solo_muestra_estudiantes_egresados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $egresado = Estudiante::factory()->create(['estado' => 'egresado', 'nombres' => 'Renata', 'apellidos' => 'Salas Cruz']);
        Estudiante::factory()->create(['estado' => 'activo', 'nombres' => 'Otro', 'apellidos' => 'Activo']);

        $this->actingAs($usuario);

        Volt::test('egresados.index')
            ->assertSee('Renata Salas Cruz')
            ->assertDontSee('Otro Activo');
    }

    public function test_el_buscador_filtra_por_nombre_o_dni(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        Estudiante::factory()->create(['estado' => 'egresado', 'nombres' => 'Renata', 'apellidos' => 'Salas Cruz', 'dni' => '11223344']);
        Estudiante::factory()->create(['estado' => 'egresado', 'nombres' => 'Jorge', 'apellidos' => 'Luna Díaz', 'dni' => '55667788']);

        $this->actingAs($usuario);

        Volt::test('egresados.index')
            ->set('termino', 'Renata')
            ->assertSee('Renata Salas Cruz')
            ->assertDontSee('Jorge Luna Díaz');
    }

    public function test_el_enlace_egresados_aparece_en_el_menu_para_coordinador(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario)
            ->get(route('egresados.index'))
            ->assertSee('Egresados');
    }

    public function test_el_enlace_egresados_no_aparece_en_el_menu_para_docente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertDontSee('Seguimiento');
    }
}
