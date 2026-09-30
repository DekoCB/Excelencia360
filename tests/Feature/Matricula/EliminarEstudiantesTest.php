<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EliminarEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_eliminar_estudiantes_es_un_borrado_reversible_que_conserva_lo_relacionado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        $eliminados = app(MatriculaService::class)->eliminarEstudiantes([$estudiante->id]);

        $this->assertSame(1, $eliminados);

        // Desaparece de las consultas normales...
        $this->assertNull(Estudiante::find($estudiante->id));
        // ...pero la fila y lo relacionado siguen en la base de datos.
        $this->assertDatabaseHas('estudiantes', ['id' => $estudiante->id]);
        $this->assertNotNull(Estudiante::withTrashed()->find($estudiante->id)->deleted_at);
        $this->assertDatabaseHas('matriculas', ['id' => $matricula->id, 'estudiante_id' => $estudiante->id]);
    }

    public function test_eliminar_varios_a_la_vez(): void
    {
        $ids = Estudiante::factory()->count(3)->create()->pluck('id')->all();

        $eliminados = app(MatriculaService::class)->eliminarEstudiantes($ids);

        $this->assertSame(3, $eliminados);
        $this->assertSame(0, Estudiante::whereIn('id', $ids)->count());
        $this->assertSame(3, Estudiante::withTrashed()->whereIn('id', $ids)->count());
    }

    public function test_coordinador_puede_seleccionar_y_eliminar_desde_el_panel(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('matricula.index')
            ->set('seleccionados', [$estudiante->id])
            ->call('eliminarSeleccionados')
            ->assertHasNoErrors();

        $this->assertNull(Estudiante::find($estudiante->id));
    }

    public function test_alternar_seleccion_todos_marca_y_desmarca_la_pagina_actual(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiantes = Estudiante::factory()->count(2)->create();

        $this->actingAs($coordinador);

        $component = Volt::test('matricula.index');
        $ids = $estudiantes->pluck('id')->sort()->values()->all();

        $component->call('alternarSeleccionTodos');
        $this->assertSame($ids, collect($component->get('seleccionados'))->sort()->values()->all());

        $component->call('alternarSeleccionTodos');
        $this->assertSame([], $component->get('seleccionados'));
    }

    public function test_un_docente_sin_permiso_no_puede_eliminar_estudiantes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('matricula.ver');
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($docente);

        rescue(fn () => Volt::test('matricula.index')
            ->set('seleccionados', [$estudiante->id])
            ->call('eliminarSeleccionados'), report: false);

        $this->assertNotNull(Estudiante::find($estudiante->id));
    }
}
