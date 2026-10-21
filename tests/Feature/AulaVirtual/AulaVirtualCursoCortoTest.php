<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Services\CursoService;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Punto 9 del backlog: un curso corto (tipo=capacitacion) tiene aula
 * virtual propia, anclada a Curso (curso_id) en vez de a Horario -- no
 * tiene ciclo/grado, así que su docente a cargo vive en Curso::docente_id
 * (ver CursoVirtual::esDeCursoCorto()).
 */
class AulaVirtualCursoCortoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function docente(): User
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        return $docente;
    }

    public function test_asignar_un_docente_a_un_curso_corto_activa_su_aula_virtual(): void
    {
        $docente = $this->docente();
        $curso = Curso::factory()->capacitacion()->create();

        $this->assertNull(CursoVirtual::query()->where('curso_id', $curso->id)->first());

        app(CursoService::class)->actualizar($curso, [
            'nombre' => $curso->nombre,
            'codigo' => $curso->codigo,
            'tipo' => 'capacitacion',
            'grado_ids' => [],
            'franjas_permitidas' => null,
            'horas' => $curso->horas,
            'documento_autorizacion' => null,
            'docente_id' => $docente->id,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('aula_virtual_cursos', ['curso_id' => $curso->id, 'activo' => true]);
    }

    public function test_activar_para_curso_corto_es_idempotente(): void
    {
        $docente = $this->docente();
        $curso = Curso::factory()->capacitacion()->create(['docente_id' => $docente->id]);
        $service = app(CursoVirtualService::class);

        $primero = $service->activarParaCursoCorto($curso);
        $segundo = $service->activarParaCursoCorto($curso);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, CursoVirtual::query()->where('curso_id', $curso->id)->count());
    }

    public function test_el_docente_a_cargo_del_curso_corto_puede_ver_y_gestionar_su_aula_virtual(): void
    {
        $docente = $this->docente();
        $curso = CursoVirtual::factory()->deCursoCorto(Curso::factory()->capacitacion()->create(['docente_id' => $docente->id]))->create();

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_otro_docente_no_puede_ver_el_aula_virtual_de_un_curso_corto_ajeno(): void
    {
        $docente = $this->docente();
        $otroDocente = $this->docente();
        $curso = CursoVirtual::factory()->deCursoCorto(Curso::factory()->capacitacion()->create(['docente_id' => $docente->id]))->create();

        $this->actingAs($otroDocente)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_en_el_curso_corto_puede_ver_su_aula_virtual(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cursoAcademico = Curso::factory()->capacitacion()->conDocente()->create();
        $curso = CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();

        MatriculaCursoCorto::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $cursoAcademico->id,
        ]);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_un_estudiante_no_matriculado_en_el_curso_corto_no_puede_ver_su_aula_virtual(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $curso = CursoVirtual::factory()->deCursoCorto()->create();

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_el_listado_muestra_los_cursos_cortos_del_docente_en_una_seccion_aparte(): void
    {
        $docente = $this->docente();
        $cursoAcademico = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica', 'docente_id' => $docente->id]);
        CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();

        $this->actingAs($docente);

        Volt::test('aula-virtual.index')
            ->assertSee('Cursos cortos')
            ->call('elegirModalidad', 'curso_corto')
            ->assertSee('Ofimática Básica');
    }

    public function test_el_listado_muestra_los_cursos_cortos_del_estudiante_matriculado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cursoAcademico = Curso::factory()->capacitacion()->conDocente()->create(['nombre' => 'Inglés Básico']);
        CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();
        MatriculaCursoCorto::factory()->create(['estudiante_id' => $estudiante->id, 'curso_id' => $cursoAcademico->id]);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.index')
            ->assertSee('Cursos cortos')
            ->call('elegirModalidad', 'curso_corto')
            ->assertSee('Inglés Básico');
    }

    public function test_la_pagina_del_curso_corto_muestra_su_nombre_y_oculta_la_pestana_de_evaluaciones(): void
    {
        $docente = $this->docente();
        $cursoAcademico = Curso::factory()->capacitacion()->create(['nombre' => 'Primeros Auxilios', 'docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk()
            ->assertSee('Primeros Auxilios')
            ->assertDontSee('Evaluaciones');
    }

    public function test_el_docente_puede_crear_un_material_en_el_aula_virtual_de_un_curso_corto(): void
    {
        $docente = $this->docente();
        $cursoAcademico = Curso::factory()->capacitacion()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Guía de bienvenida')
            ->set('materialUrl', 'https://example.com/guia')
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('materiales', ['curso_virtual_id' => $curso->id, 'titulo' => 'Guía de bienvenida']);
    }

    public function test_tareas_del_estudiante_incluye_las_de_sus_cursos_cortos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $cursoAcademico = Curso::factory()->capacitacion()->conDocente()->create();
        $curso = CursoVirtual::factory()->deCursoCorto($cursoAcademico)->create();
        MatriculaCursoCorto::factory()->create(['estudiante_id' => $estudiante->id, 'curso_id' => $cursoAcademico->id]);

        $tarea = Tarea::factory()->create(['curso_virtual_id' => $curso->id]);

        $tareas = app(TareaService::class)->delEstudiante($estudiante);

        $this->assertCount(1, $tareas);
        $this->assertSame($tarea->id, $tareas->first()->id);
    }
}
