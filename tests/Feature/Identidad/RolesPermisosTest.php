<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RolesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_direccion_puede_alternar_un_permiso_de_un_rol(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->assertFalse($docente->hasPermissionTo('certificados.ver'));

        Volt::test('roles.index')
            ->call('alternar', RolEnum::DOCENTE->value, 'certificados.ver');

        $this->assertTrue($docente->fresh()->hasPermissionTo('certificados.ver'));
    }

    public function test_un_rol_sin_permiso_no_puede_acceder_a_la_matriz(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get('/roles')
            ->assertForbidden();
    }
}
