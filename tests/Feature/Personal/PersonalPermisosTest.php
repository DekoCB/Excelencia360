<?php

namespace Tests\Feature\Personal;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PersonalPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_el_listado_de_personal(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('personal.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_listado_de_personal(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)->get(route('personal.index'))->assertForbidden();
    }

    public function test_el_buscador_de_personal_muestra_sugerencias_y_filtra(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        Personal::factory()->create(['nombres' => 'Elena', 'apellidos' => 'Vargas Ruiz']);
        Personal::factory()->create(['nombres' => 'Tomas', 'apellidos' => 'Cardenas Leon']);

        $this->actingAs($coordinador);

        $html = Volt::test('personal.index')->html();
        $this->assertStringContainsString('\u0022label\u0022:\u0022Elena Vargas Ruiz\u0022', $html);
        $this->assertStringContainsString('\u0022label\u0022:\u0022Tomas Cardenas Leon\u0022', $html);

        Volt::test('personal.index')
            ->set('termino', 'Elena')
            ->assertSee('Elena Vargas Ruiz')
            ->assertDontSee('Tomas Cardenas Leon');
    }

    public function test_coordinador_registra_una_persona_nueva(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('personal.index')
            ->call('abrirModalCrear')
            ->set('nombres', 'Rosa')
            ->set('apellidos', 'Mendoza Díaz')
            ->set('dni', '41234567')
            ->set('celular', '987654321')
            ->set('cargo', 'Psicóloga')
            ->set('area', 'Bienestar Estudiantil')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $this->assertDatabaseHas('personal', [
            'dni' => '41234567',
            'cargo' => 'Psicóloga',
            'activo' => true,
        ]);
    }

    public function test_no_permite_registrar_dos_personas_con_el_mismo_dni(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Personal::factory()->create(['dni' => '11223344']);

        $this->actingAs($coordinador);

        Volt::test('personal.index')
            ->call('abrirModalCrear')
            ->set('nombres', 'Otra')
            ->set('apellidos', 'Persona')
            ->set('dni', '11223344')
            ->set('cargo', 'Portero')
            ->call('guardar')
            ->assertHasErrors('dni');
    }

    public function test_coordinador_edita_el_cargo_y_desactiva_a_una_persona(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $persona = Personal::factory()->create(['cargo' => 'Portero', 'activo' => true]);

        $this->actingAs($coordinador);

        Volt::test('personal.index')
            ->call('abrirModalEditar', $persona->id)
            ->set('cargo', 'Vigilante nocturno')
            ->set('activo', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $persona->refresh();
        $this->assertSame('Vigilante nocturno', $persona->cargo);
        $this->assertFalse($persona->activo);
    }

    public function test_un_docente_no_puede_gestionar_personal(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $persona = Personal::factory()->create();

        rescue(fn () => Volt::test('personal.index')
            ->call('abrirModalEditar', $persona->id), report: false);

        $this->assertNotNull($persona->fresh());
    }

    public function test_coordinador_puede_ver_la_pagina_de_carga_masiva(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('personal.carga-masiva'))->assertOk();
    }

    public function test_procesar_carga_masiva_registra_al_personal_de_la_plantilla(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        $archivo = UploadedFile::fake()->createWithContent(
            'personal.csv',
            "nombres,apellidos,dni,celular,cargo,area,fecha_ingreso\n".
            "Rosa,Mendoza Díaz,41234567,987654321,Psicóloga,Bienestar Estudiantil,01/03/2024\n".
            "Jorge,Salazar Vega,71234567,,Portero,,\n"
        );

        Volt::test('personal.carga-masiva')
            ->set('archivo', $archivo)
            ->call('procesar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personal', ['dni' => '41234567']);
        $this->assertDatabaseHas('personal', ['dni' => '71234567']);
        $this->assertSame(2, Personal::query()->count());
    }
}
