<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\ProgramaEstudio;
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
        $programa = ProgramaEstudio::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('academico.grados.index')
            ->set('programaEstudioId', (string) $programa->id)
            ->set('nombre', 'Grado 1')
            ->set('orden', '1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grados', ['nombre' => 'Grado 1', 'programa_estudio_id' => $programa->id]);
    }

    public function test_coordinador_puede_ver_programas_de_estudio(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get('/academico/programas-estudio')
            ->assertOk();
    }

    public function test_coordinador_puede_crear_un_programa_de_estudio(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('academico.programas-estudio.index')
            ->set('nombre', 'Contabilidad')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('programas_estudio', ['nombre' => 'Contabilidad']);
    }

    public function test_un_docente_no_puede_gestionar_programas_de_estudio(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        // La migración siembra un "Programa de Estudio General" provisional
        // -- la tabla nunca arranca realmente vacía.
        $totalAntes = ProgramaEstudio::query()->count();

        $this->actingAs($docente);

        rescue(fn () => Volt::test('academico.programas-estudio.index')
            ->call('abrirModal'), report: false);

        $this->assertDatabaseCount('programas_estudio', $totalAntes);
    }

    public function test_el_orden_de_un_semestre_es_unico_por_programa_pero_puede_repetirse_entre_programas(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $programaA = ProgramaEstudio::factory()->create();
        $programaB = ProgramaEstudio::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('academico.grados.index')
            ->set('programaEstudioId', (string) $programaA->id)
            ->set('nombre', 'Semestre 1 de A')
            ->set('orden', '1')
            ->call('guardar')
            ->assertHasNoErrors();

        // Mismo orden 1, pero en otro programa: no debería chocar.
        Volt::test('academico.grados.index')
            ->set('programaEstudioId', (string) $programaB->id)
            ->set('nombre', 'Semestre 1 de B')
            ->set('orden', '1')
            ->call('guardar')
            ->assertHasNoErrors();

        // El mismo orden otra vez en el programa A sí debería chocar.
        Volt::test('academico.grados.index')
            ->set('programaEstudioId', (string) $programaA->id)
            ->set('nombre', 'Otro semestre 1 de A')
            ->set('orden', '1')
            ->call('guardar')
            ->assertHasErrors('orden');

        $this->assertDatabaseCount('grados', 2);
    }
}
