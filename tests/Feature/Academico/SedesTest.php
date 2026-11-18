<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Sede;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SedesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_la_migracion_crea_una_sede_principal_por_defecto(): void
    {
        $this->assertDatabaseHas('sedes', ['nombre' => 'Sede Principal']);
    }

    public function test_coordinador_puede_crear_una_sede_nueva(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.aulas.index')
            ->call('abrirModalSede')
            ->set('sedeNombre', 'Sede Huancayo')
            ->set('sedeDireccion', 'Av. Siempre Viva 123')
            ->call('guardarSede')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sedes', ['nombre' => 'Sede Huancayo', 'direccion' => 'Av. Siempre Viva 123']);
    }

    public function test_un_aula_nueva_puede_asignarse_a_una_sede(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $sede = Sede::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('academico.aulas.index')
            ->call('abrirModal')
            ->set('sedeId', (string) $sede->id)
            ->set('nombre', 'Aula 101')
            ->set('capacidad', '30')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('aulas', ['nombre' => 'Aula 101', 'sede_id' => $sede->id]);
    }

    public function test_el_listado_agrupa_las_aulas_por_sede(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $sede = Sede::factory()->create(['nombre' => 'Sede Huancayo']);
        Aula::factory()->create(['sede_id' => $sede->id, 'nombre' => 'Aula de la sede nueva']);

        $this->actingAs($coordinador);

        Volt::test('academico.aulas.index')
            ->assertSee('Sede Huancayo')
            ->assertSee('Aula de la sede nueva');
    }
}
