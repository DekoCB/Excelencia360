<?php

namespace Tests\Feature\Personal;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Punto 12 del backlog: Personal gana una ficha propia más rica (antes
 * solo existía el modal de crear/editar sobre el listado). No tiene
 * sección de Contrato -- Personal no tiene cuenta de login (ver
 * Personal::class).
 */
class PersonalFichaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_la_ficha_de_personal(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $persona = Personal::factory()->create(['nombres' => 'Rosa', 'apellidos' => 'Medina']);

        $this->actingAs($coordinador)
            ->get(route('personal.show', $persona))
            ->assertOk()
            ->assertSee('Rosa Medina')
            ->assertDontSee('Contratos');
    }

    public function test_un_docente_no_puede_ver_la_ficha_de_personal(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $persona = Personal::factory()->create();

        $this->actingAs($docente)
            ->get(route('personal.show', $persona))
            ->assertForbidden();
    }

    public function test_coordinador_edita_los_datos_desde_la_ficha(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $persona = Personal::factory()->create(['cargo' => 'Portero']);

        $this->actingAs($coordinador);

        Volt::test('personal.show', ['personal' => $persona])
            ->set('cargo', 'Portero principal')
            ->call('guardarDatos')
            ->assertHasNoErrors();

        $this->assertSame('Portero principal', $persona->fresh()->cargo);
    }

    public function test_la_ficha_muestra_la_antiguedad_cuando_hay_fecha_de_ingreso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $persona = Personal::factory()->create(['fecha_ingreso' => now()->subYears(2)]);

        $this->actingAs($coordinador)
            ->get(route('personal.show', $persona))
            ->assertSee('Antigüedad');
    }

    public function test_un_administrativo_sin_permiso_de_gestionar_no_puede_editar_desde_la_ficha(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $persona = Personal::factory()->create(['cargo' => 'Portero']);

        $this->actingAs($administrativo);

        rescue(fn () => Volt::test('personal.show', ['personal' => $persona])
            ->set('cargo', 'Otro cargo')
            ->call('guardarDatos'), report: false);

        $this->assertSame('Portero', $persona->fresh()->cargo);
    }

    public function test_el_listado_enlaza_a_la_ficha_de_cada_persona(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $persona = Personal::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('personal.index')
            ->assertSee(route('personal.show', $persona));
    }
}
