<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\SeccionAcademica;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SeccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_la_migracion_siembra_las_cinco_secciones_iniciales(): void
    {
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], SeccionAcademica::query()->orderBy('letra')->pluck('letra')->all());
    }

    public function test_coordinador_puede_crear_una_seccion_nueva(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.secciones.index')
            ->call('abrirModal')
            ->set('letra', 'F')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('secciones_academicas', ['letra' => 'F', 'activa' => true]);
    }

    public function test_no_se_puede_repetir_la_misma_letra(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.secciones.index')
            ->call('abrirModal')
            ->set('letra', 'A')
            ->call('guardar')
            ->assertHasErrors('letra');
    }

    public function test_un_docente_no_puede_crear_secciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente);

        Volt::test('academico.secciones.index')->assertForbidden();
    }
}
