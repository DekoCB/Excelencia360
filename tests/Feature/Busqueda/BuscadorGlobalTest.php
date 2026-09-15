<?php

namespace Tests\Feature\Busqueda;

use App\Models\User;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BuscadorGlobalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function coordinador(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        return $usuario;
    }

    public function test_el_campo_de_busqueda_aparece_en_cualquier_pagina_del_panel(): void
    {
        $this->actingAs($this->coordinador())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buscar estudiante, docente, personal');
    }

    public function test_cerrar_limpia_el_termino_de_busqueda(): void
    {
        $this->actingAs($this->coordinador());

        Volt::test('busqueda.buscador-global')
            ->set('termino', 'algo')
            ->call('cerrar')
            ->assertSet('termino', '');
    }

    public function test_escribir_menos_de_dos_caracteres_no_muestra_nada(): void
    {
        Estudiante::factory()->create(['nombres' => 'Ana']);

        $this->actingAs($this->coordinador());

        Volt::test('busqueda.buscador-global')
            ->set('termino', 'A')
            ->assertDontSee('Sin resultados')
            ->assertDontSee('Ana');
    }

    public function test_escribir_un_termino_valido_muestra_resultados_de_estudiantes(): void
    {
        Estudiante::factory()->create(['nombres' => 'Yolanda Beatriz', 'apellidos' => 'Chura Vilca']);

        $this->actingAs($this->coordinador());

        Volt::test('busqueda.buscador-global')
            ->set('termino', 'Yolanda Beatriz')
            ->assertSee('Chura Vilca')
            ->assertSee('Estudiante');
    }

    public function test_sin_coincidencias_muestra_mensaje_de_sin_resultados(): void
    {
        $this->actingAs($this->coordinador());

        Volt::test('busqueda.buscador-global')
            ->set('termino', 'Nadie Con Este Nombre')
            ->assertSee('Sin resultados');
    }

    public function test_docentes_index_prefiltra_por_el_query_param_q(): void
    {
        $docenteBuscado = User::factory()->create(['name' => 'Rosmery Apaza Condori', 'dni' => '65478932']);
        Docente::factory()->create(['user_id' => $docenteBuscado->id]);

        $otroDocente = User::factory()->create(['name' => 'Docente Que No Debe Aparecer']);
        Docente::factory()->create(['user_id' => $otroDocente->id]);

        $this->actingAs($this->coordinador())
            ->get(route('docentes.index', ['q' => '65478932']))
            ->assertOk()
            ->assertSee('Rosmery Apaza Condori')
            ->assertDontSee('Docente Que No Debe Aparecer');
    }

    public function test_personal_index_prefiltra_por_el_query_param_q(): void
    {
        Personal::factory()->create(['nombres' => 'Buscado', 'apellidos' => 'Por DNI', 'dni' => '11223344']);
        Personal::factory()->create(['nombres' => 'No Debe', 'apellidos' => 'Aparecer']);

        $this->actingAs($this->coordinador())
            ->get(route('personal.index', ['q' => '11223344']))
            ->assertOk()
            ->assertSee('Buscado Por DNI')
            ->assertDontSee('No Debe Aparecer');
    }
}
