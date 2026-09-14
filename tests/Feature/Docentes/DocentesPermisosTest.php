<?php

namespace Tests\Feature\Docentes;

use App\Models\User;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DocentesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_el_listado_de_docentes(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('docentes.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_listado_de_docentes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)->get(route('docentes.index'))->assertForbidden();
    }

    public function test_el_buscador_de_docentes_muestra_sugerencias_y_filtra(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docenteA = Docente::factory()->create();
        $docenteA->usuario->update(['name' => 'Marcos Huaman']);
        $docenteB = Docente::factory()->create();
        $docenteB->usuario->update(['name' => 'Rocio Salazar']);

        $this->actingAs($coordinador);

        // @js() codifica las comillas como " dentro del atributo x-data.
        $html = Volt::test('docentes.index')->html();
        $this->assertStringContainsString('\u0022label\u0022:\u0022Marcos Huaman\u0022', $html);
        $this->assertStringContainsString('\u0022label\u0022:\u0022Rocio Salazar\u0022', $html);

        Volt::test('docentes.index')
            ->set('termino', 'Marcos')
            ->assertSee('Marcos Huaman')
            ->assertDontSee('Rocio Salazar');
    }

    public function test_coordinador_registra_un_docente_nuevo_y_le_crea_su_cuenta(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('docentes.index')
            ->call('abrirModalCrear')
            ->set('nombres', 'Ana')
            ->set('apellidos', 'Torres Quispe')
            ->set('dni', '45678912')
            ->set('celular', '987654321')
            ->set('especialidad', 'Matemática')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $usuario = User::query()->where('dni', '45678912')->first();

        $this->assertNotNull($usuario);
        $this->assertSame('45678912@ceba.test', $usuario->email);
        $this->assertTrue($usuario->hasRole(RolEnum::DOCENTE->value));
        $this->assertDatabaseHas('docentes', ['user_id' => $usuario->id, 'especialidad' => 'Matemática']);
    }

    public function test_no_permite_registrar_dos_docentes_con_el_mismo_dni(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        User::factory()->create(['dni' => '11223344']);

        $this->actingAs($coordinador);

        Volt::test('docentes.index')
            ->call('abrirModalCrear')
            ->set('nombres', 'Otro')
            ->set('apellidos', 'Docente')
            ->set('dni', '11223344')
            ->call('guardar')
            ->assertHasErrors('dni');
    }

    public function test_coordinador_edita_la_especialidad_de_un_docente_existente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = Docente::factory()->create(['especialidad' => 'Comunicación']);

        $this->actingAs($coordinador);

        Volt::test('docentes.index')
            ->call('abrirModalEditar', $docente->id)
            ->set('especialidad', 'Inglés')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Inglés', $docente->fresh()->especialidad);
    }

    public function test_un_docente_no_puede_gestionar_docentes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $otroDocente = Docente::factory()->create();

        rescue(fn () => Volt::test('docentes.index')
            ->call('abrirModalEditar', $otroDocente->id), report: false);

        $this->assertNotNull($otroDocente->fresh());
    }

    public function test_coordinador_puede_ver_la_pagina_de_carga_masiva(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('docentes.carga-masiva'))->assertOk();
    }

    public function test_procesar_carga_masiva_registra_los_docentes_de_la_plantilla(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        $archivo = UploadedFile::fake()->createWithContent(
            'docentes.csv',
            "nombres,apellidos,dni,celular,especialidad,grado_academico,fecha_ingreso\n".
            "Ana,Torres Quispe,45678912,987654321,Matemática,Licenciado,01/03/2024\n".
            "Luis,Ramírez Soto,78912345,,,,\n"
        );

        Volt::test('docentes.carga-masiva')
            ->set('archivo', $archivo)
            ->call('procesar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['dni' => '45678912']);
        $this->assertDatabaseHas('users', ['dni' => '78912345']);
        $this->assertSame(2, Docente::query()->count());
    }
}
