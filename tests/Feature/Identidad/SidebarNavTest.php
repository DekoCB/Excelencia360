<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión: Dirección tiene todos los permisos vía '*', incluyendo
 * pagos.ver_propio y certificados.solicitar — pero esas páginas también
 * exigen una ficha de Estudiante, que una cuenta Dirección no tiene. Sin
 * este filtro adicional en el sidebar, el enlace se mostraba y el clic
 * terminaba en 403.
 */
class SidebarNavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_direccion_no_ve_enlaces_exclusivos_de_estudiante(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi estado de cuenta')
            ->assertDontSee('Mis certificados');
    }

    public function test_estudiante_si_ve_sus_propios_enlaces(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mi estado de cuenta')
            ->assertSee('Mis certificados');
    }
}
