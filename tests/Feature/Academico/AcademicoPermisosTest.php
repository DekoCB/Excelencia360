<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AcademicoPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_grados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get('/academico/grados')
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_modulo_academico(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get('/academico/ciclos')
            ->assertForbidden();
    }

    public function test_coordinador_puede_crear_un_grado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.grados.index')
            ->set('nombre', 'Grado 1')
            ->set('orden', '1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grados', ['nombre' => 'Grado 1']);
    }
}
