<?php

namespace Tests\Feature\Vacaciones;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VacacionesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_vacaciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario)->get(route('vacaciones.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_vacaciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($usuario)->get(route('vacaciones.index'))->assertForbidden();
    }

    public function test_registrar_vacaciones_desde_la_ui_las_deja_visibles_en_vigentes(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create(['name' => 'Walter Quispe Paredes']);
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($usuario);

        Volt::test('vacaciones.index')
            ->call('abrirModal')
            ->set('terminoBusqueda', 'Quispe Paredes')
            ->call('seleccionarUsuario', $docente->id, $docente->name)
            ->set('fechaInicio', now()->format('Y-m-d'))
            ->set('fechaFin', now()->addDays(15)->format('Y-m-d'))
            ->call('activar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $this->assertDatabaseHas('vacaciones', ['user_id' => $docente->id]);
    }
}
