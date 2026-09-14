<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Services\ClaseGrabadaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoDelDocente(User $docente): CursoVirtual
    {
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    public function test_el_docente_dueno_del_curso_puede_verlo(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_curso_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_en_el_grado_y_ciclo_del_curso_puede_verlo(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_un_estudiante_matriculado_puede_responder_en_un_foro(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        $foro = Foro::factory()->create(['curso_virtual_id' => $curso->id, 'autor_id' => $docente->id]);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set("nuevaRespuestaForo.{$foro->id}", 'Mi respuesta')
            ->call('responderForo', $foro->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('foro_respuestas', [
            'foro_id' => $foro->id,
            'autor_id' => $usuario->id,
            'contenido' => 'Mi respuesta',
        ]);
    }

    public function test_un_estudiante_no_matriculado_en_ese_grado_y_ciclo_no_puede_ver_el_curso(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_solo_el_docente_dueno_puede_crear_materiales(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);

        $this->assertTrue($otroDocente->can('manage', $curso) === false);
        $this->assertTrue($docente->can('manage', $curso));
    }

    public function test_el_docente_puede_subir_un_material_a_varias_de_sus_secciones_a_la_vez(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $horarioUno = Horario::factory()->create(['docente_id' => $docente->id]);
        $cursoUno = CursoVirtual::factory()->create(['horario_id' => $horarioUno->id]);

        $horarioDos = Horario::factory()->create(['docente_id' => $docente->id]);
        $cursoDos = CursoVirtual::factory()->create(['horario_id' => $horarioDos->id]);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $cursoUno])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Video de repaso')
            ->set('materialUrl', 'https://ejemplo.test/video')
            ->set('materialCursosSeleccionados', [$cursoUno->id, $cursoDos->id])
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $this->assertSame(1, $cursoUno->materiales()->count());
        $this->assertSame(1, $cursoDos->materiales()->count());
    }

    public function test_el_material_creado_con_semana_se_agrupa_bajo_su_semana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Video de repaso')
            ->set('materialUrl', 'https://ejemplo.test/video')
            ->set('materialSemana', '2')
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Semana 2', 'Video de repaso']);
    }

    public function test_el_material_creado_sin_semana_se_agrupa_bajo_bienvenida(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Video de bienvenida')
            ->set('materialUrl', 'https://ejemplo.test/video')
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Bienvenida', 'Video de bienvenida']);
    }

    public function test_no_permite_subir_un_php_disfrazado_de_material(): void
    {
        Storage::fake('public');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'archivo')
            ->set('materialTitulo', 'Material')
            ->set('materialArchivo', UploadedFile::fake()->create('malicioso.php', 10, 'application/x-php'))
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasErrors('materialArchivo');

        $this->assertSame(0, $curso->materiales()->count());
    }

    public function test_un_docente_no_puede_subir_material_a_la_seccion_de_otro_docente_inyectando_el_id(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $miCurso = $this->cursoDelDocente($docente);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $cursoAjeno = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $miCurso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Video de repaso')
            ->set('materialUrl', 'https://ejemplo.test/video')
            ->set('materialCursosSeleccionados', [$miCurso->id, $cursoAjeno->id])
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $this->assertSame(1, $miCurso->materiales()->count());
        $this->assertSame(0, $cursoAjeno->materiales()->count());
    }

    public function test_el_docente_puede_subir_una_clase_grabada_a_varias_de_sus_secciones_a_la_vez(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $horarioUno = Horario::factory()->create(['docente_id' => $docente->id]);
        $cursoUno = CursoVirtual::factory()->create(['horario_id' => $horarioUno->id]);

        $horarioDos = Horario::factory()->create(['docente_id' => $docente->id]);
        $cursoDos = CursoVirtual::factory()->create(['horario_id' => $horarioDos->id]);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $cursoUno])
            ->set('grabacionTipo', 'enlace')
            ->set('grabacionTitulo', 'Clase del 15 de julio')
            ->set('grabacionUrl', 'https://youtube.test/clase')
            ->set('grabacionCursosSeleccionados', [$cursoUno->id, $cursoDos->id])
            ->call('crearGrabacion')
            ->assertHasNoErrors();

        $this->assertSame(1, $cursoUno->clasesGrabadas()->count());
        $this->assertSame(1, $cursoDos->clasesGrabadas()->count());
    }

    public function test_un_docente_no_puede_subir_clase_grabada_a_la_seccion_de_otro_docente_inyectando_el_id(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $miCurso = $this->cursoDelDocente($docente);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $cursoAjeno = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $miCurso])
            ->set('grabacionTipo', 'enlace')
            ->set('grabacionTitulo', 'Clase del 15 de julio')
            ->set('grabacionUrl', 'https://youtube.test/clase')
            ->set('grabacionCursosSeleccionados', [$miCurso->id, $cursoAjeno->id])
            ->call('crearGrabacion')
            ->assertHasNoErrors();

        $this->assertSame(1, $miCurso->clasesGrabadas()->count());
        $this->assertSame(0, $cursoAjeno->clasesGrabadas()->count());
    }

    public function test_no_permite_subir_un_php_disfrazado_de_grabacion(): void
    {
        Storage::fake('public');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('grabacionTipo', 'archivo')
            ->set('grabacionTitulo', 'Clase grabada')
            ->set('grabacionArchivo', UploadedFile::fake()->create('malicioso.php', 10, 'application/x-php'))
            ->set('grabacionCursosSeleccionados', [$curso->id])
            ->call('crearGrabacion')
            ->assertHasErrors('grabacionArchivo');

        $this->assertSame(0, $curso->clasesGrabadas()->count());
    }

    public function test_un_estudiante_matriculado_ve_las_clases_grabadas_pero_no_puede_crear_una(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->app->make(ClaseGrabadaService::class)
            ->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase del 15 de julio', 'https://youtube.test/clase', null);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tab', 'clases-grabadas')
            ->assertSee('Clase del 15 de julio')
            ->assertDontSee('Nueva clase grabada');
    }

    public function test_el_foro_creado_con_semana_se_agrupa_bajo_su_semana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tab', 'foros')
            ->set('foroTitulo', 'Dudas de la semana 2')
            ->set('foroSemana', '2')
            ->call('crearForo')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Semana 2', 'Dudas de la semana 2']);
    }

    public function test_el_foro_creado_sin_semana_se_agrupa_bajo_bienvenida(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tab', 'foros')
            ->set('foroTitulo', 'Introduccion al curso')
            ->call('crearForo')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Bienvenida', 'Introduccion al curso']);
    }

    public function test_coordinador_puede_replicar_una_tarea_a_otro_curso_virtual_de_otro_docente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create([
            'curso_id' => $horarioA->curso_id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);
        $cursoVirtualA = CursoVirtual::factory()->create(['horario_id' => $horarioA->id]);
        $cursoVirtualB = CursoVirtual::factory()->create(['horario_id' => $horarioB->id]);

        $this->actingAs($coordinador);

        Volt::test('aula-virtual.show', ['curso' => $cursoVirtualA])
            ->set('tareaTitulo', 'Práctica 1')
            ->set('tareaFechaLimite', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('tareaPuntajeMax', '20')
            ->set('tareaCursosSeleccionados', [$cursoVirtualA->id, $cursoVirtualB->id])
            ->call('crearTarea')
            ->assertHasNoErrors();

        $this->assertSame(1, $cursoVirtualA->tareas()->count());
        $this->assertSame(1, $cursoVirtualB->tareas()->count());
    }

    public function test_un_docente_no_puede_replicar_una_tarea_al_curso_de_otro_docente_inyectando_el_id(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $miCurso = $this->cursoDelDocente($docente);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $cursoAjeno = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $miCurso])
            ->set('tareaTitulo', 'Práctica 1')
            ->set('tareaFechaLimite', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('tareaPuntajeMax', '20')
            ->set('tareaCursosSeleccionados', [$miCurso->id, $cursoAjeno->id])
            ->call('crearTarea')
            ->assertHasNoErrors();

        $this->assertSame(1, $miCurso->tareas()->count());
        $this->assertSame(0, $cursoAjeno->tareas()->count());
    }

    public function test_coordinador_puede_replicar_un_foro_a_otro_curso_virtual_de_otro_docente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create([
            'curso_id' => $horarioA->curso_id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);
        $cursoVirtualA = CursoVirtual::factory()->create(['horario_id' => $horarioA->id]);
        $cursoVirtualB = CursoVirtual::factory()->create(['horario_id' => $horarioB->id]);

        $this->actingAs($coordinador);

        Volt::test('aula-virtual.show', ['curso' => $cursoVirtualA])
            ->set('foroTitulo', 'Consultas generales')
            ->set('foroCursosSeleccionados', [$cursoVirtualA->id, $cursoVirtualB->id])
            ->call('crearForo')
            ->assertHasNoErrors();

        $this->assertSame(1, $cursoVirtualA->foros()->count());
        $this->assertSame(1, $cursoVirtualB->foros()->count());
    }

    public function test_direccion_puede_gestionar_cualquier_curso(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->assertTrue($direccion->can('manage', $curso));
    }

    public function test_coordinador_puede_gestionar_cualquier_curso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->assertTrue($coordinador->can('manage', $curso));
    }

    public function test_coordinador_puede_subir_la_imagen_de_portada_del_curso(): void
    {
        Storage::fake('public');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($coordinador);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('nuevaPortada', UploadedFile::fake()->image('portada.jpg'))
            ->assertHasNoErrors();

        $this->assertNotNull($curso->horario->curso->fresh()->getFirstMedia('portada'));
    }

    public function test_un_docente_no_puede_subir_la_imagen_de_portada(): void
    {
        Storage::fake('public');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('nuevaPortada', UploadedFile::fake()->image('portada.jpg'))
            ->assertStatus(403);

        $this->assertNull($curso->horario->curso->fresh()->getFirstMedia('portada'));
    }

    public function test_coordinador_puede_ver_el_listado_de_cursos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('aula-virtual.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_permisos_de_aula_virtual_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.index'))
            ->assertForbidden();
    }

    /**
     * Dirección tiene aula_virtual.gestionar_propio vía '*' sin ser docente:
     * debía mostrar la vista de supervisión, no la de "tus cursos" (vacía).
     */
    public function test_direccion_ve_la_supervision_general_no_la_vista_de_docente(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $this->cursoDelDocente($docente);

        $this->actingAs($direccion)
            ->get(route('aula-virtual.index'))
            ->assertOk()
            ->assertSee('Todos los cursos virtuales activos.')
            ->assertDontSee('Todavía no tienes cursos con aula virtual activada.');
    }
}
