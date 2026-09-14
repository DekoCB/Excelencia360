<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Services\ClaseGrabadaService;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Models\Notificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AulaVirtualServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cursoVirtualService(): CursoVirtualService
    {
        return $this->app->make(CursoVirtualService::class);
    }

    public function test_activar_para_horario_es_idempotente(): void
    {
        $horario = Horario::factory()->create();

        $primero = $this->cursoVirtualService()->activarParaHorario($horario);
        $segundo = $this->cursoVirtualService()->activarParaHorario($horario);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, CursoVirtual::query()->count());
    }

    public function test_del_estudiante_no_mezcla_otros_grados_o_ciclos(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        $cursoA = $this->cursoVirtualService()->activarParaHorario($horarioA);
        $cursoB = $this->cursoVirtualService()->activarParaHorario($horarioB);

        $estudianteA = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteA->id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);

        $cursos = $this->cursoVirtualService()->delEstudiante($estudianteA);

        $this->assertTrue($cursos->contains('id', $cursoA->id));
        $this->assertFalse($cursos->contains('id', $cursoB->id));
    }

    public function test_cursos_virtuales_relacionados_incluye_otros_docentes_del_mismo_curso_grado_y_ciclo(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create([
            'curso_id' => $horarioA->curso_id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);
        $cursoVirtualA = $this->cursoVirtualService()->activarParaHorario($horarioA);
        $cursoVirtualB = $this->cursoVirtualService()->activarParaHorario($horarioB);

        // Mismo grado y ciclo, pero otro curso académico: no está
        // relacionado, aunque coincidan grado/ciclo.
        $otroHorario = Horario::factory()->create([
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);
        $cursoVirtualNoRelacionado = $this->cursoVirtualService()->activarParaHorario($otroHorario);

        $relacionados = $this->cursoVirtualService()->cursosVirtualesRelacionados($cursoVirtualA);

        $this->assertCount(2, $relacionados);
        $this->assertTrue($relacionados->contains('id', $cursoVirtualA->id));
        $this->assertTrue($relacionados->contains('id', $cursoVirtualB->id));
        $this->assertFalse($relacionados->contains('id', $cursoVirtualNoRelacionado->id));
    }

    public function test_material_de_tipo_pdf_requiere_archivo(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::PDF, 'Separata', null, null);
    }

    public function test_material_de_tipo_enlace_requiere_url(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video de repaso', null, null);
    }

    public function test_crear_material_con_archivo_lo_adjunta_a_la_coleccion_archivo(): void
    {
        Storage::fake('public');

        $curso = CursoVirtual::factory()->create();
        $archivo = UploadedFile::fake()->create('separata.pdf', 500, 'application/pdf');

        $material = $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::PDF, 'Separata', null, $archivo);

        $this->assertNotNull($material->getFirstMedia('archivo'));
    }

    public function test_crear_para_varios_crea_un_material_por_cada_curso(): void
    {
        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();

        $materiales = $this->app->make(MaterialService::class)->crearParaVarios(
            collect([$cursoUno, $cursoDos]),
            TipoMaterialEnum::ENLACE,
            'Video de repaso',
            'https://ejemplo.test/video',
            null,
        );

        $this->assertCount(2, $materiales);
        $this->assertSame(1, $cursoUno->materiales()->count());
        $this->assertSame(1, $cursoDos->materiales()->count());
        $this->assertSame('Video de repaso', $cursoUno->materiales()->first()->titulo);
        $this->assertSame('Video de repaso', $cursoDos->materiales()->first()->titulo);
    }

    public function test_crear_para_varios_con_archivo_lo_adjunta_en_cada_material(): void
    {
        Storage::fake('public');

        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();
        $archivo = UploadedFile::fake()->create('separata.pdf', 500, 'application/pdf');

        $materiales = $this->app->make(MaterialService::class)->crearParaVarios(
            collect([$cursoUno, $cursoDos]),
            TipoMaterialEnum::PDF,
            'Separata',
            null,
            $archivo,
        );

        $this->assertNotNull($materiales[0]->getFirstMedia('archivo'));
        $this->assertNotNull($materiales[1]->getFirstMedia('archivo'));
    }

    public function test_crear_para_varios_sin_archivo_requerido_lanza_excepcion_sin_crear_nada(): void
    {
        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();

        try {
            $this->app->make(MaterialService::class)->crearParaVarios(
                collect([$cursoUno, $cursoDos]),
                TipoMaterialEnum::PDF,
                'Separata',
                null,
                null,
            );
            $this->fail('Se esperaba una ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(0, $cursoUno->materiales()->count());
            $this->assertSame(0, $cursoDos->materiales()->count());
        }
    }

    public function test_crear_material_persiste_la_semana_indicada(): void
    {
        $curso = CursoVirtual::factory()->create();

        $material = $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video', 'https://ejemplo.test/video', null, 3);

        $this->assertSame(3, $material->fresh()->semana);
    }

    public function test_crear_material_sin_semana_la_deja_nula(): void
    {
        $curso = CursoVirtual::factory()->create();

        $material = $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video', 'https://ejemplo.test/video', null);

        $this->assertNull($material->fresh()->semana);
    }

    public function test_clase_grabada_de_tipo_archivo_requiere_archivo(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ARCHIVO, 'Clase del 15 de julio', null, null);
    }

    public function test_clase_grabada_de_tipo_enlace_requiere_url(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase del 15 de julio', null, null);
    }

    public function test_crear_clase_grabada_con_archivo_lo_adjunta_a_la_coleccion_video(): void
    {
        Storage::fake('public');

        $curso = CursoVirtual::factory()->create();
        $archivo = UploadedFile::fake()->create('clase.mp4', 5000, 'video/mp4');

        $claseGrabada = $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ARCHIVO, 'Clase del 15 de julio', null, $archivo);

        $this->assertNotNull($claseGrabada->getFirstMedia('video'));
    }

    public function test_crear_clase_grabada_con_enlace_no_exige_archivo(): void
    {
        $curso = CursoVirtual::factory()->create();

        $claseGrabada = $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase del 15 de julio', 'https://youtube.test/clase', null);

        $this->assertSame('https://youtube.test/clase', $claseGrabada->url);
        $this->assertNull($claseGrabada->getFirstMedia('video'));
    }

    public function test_crear_clase_grabada_para_varios_crea_una_por_cada_curso(): void
    {
        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();

        $clasesGrabadas = $this->app->make(ClaseGrabadaService::class)->crearParaVarios(
            collect([$cursoUno, $cursoDos]),
            TipoClaseGrabadaEnum::ENLACE,
            'Clase del 15 de julio',
            'https://youtube.test/clase',
            null,
        );

        $this->assertCount(2, $clasesGrabadas);
        $this->assertSame(1, $cursoUno->clasesGrabadas()->count());
        $this->assertSame(1, $cursoDos->clasesGrabadas()->count());
    }

    public function test_crear_clase_grabada_para_varios_con_archivo_lo_adjunta_en_cada_una(): void
    {
        Storage::fake('public');

        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();
        $archivo = UploadedFile::fake()->create('clase.mp4', 5000, 'video/mp4');

        $clasesGrabadas = $this->app->make(ClaseGrabadaService::class)->crearParaVarios(
            collect([$cursoUno, $cursoDos]),
            TipoClaseGrabadaEnum::ARCHIVO,
            'Clase del 15 de julio',
            null,
            $archivo,
        );

        $this->assertNotNull($clasesGrabadas[0]->getFirstMedia('video'));
        $this->assertNotNull($clasesGrabadas[1]->getFirstMedia('video'));
    }

    public function test_crear_clase_grabada_persiste_la_semana_indicada(): void
    {
        $curso = CursoVirtual::factory()->create();

        $claseGrabada = $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase del 15 de julio', 'https://youtube.test/clase', null, 2);

        $this->assertSame(2, $claseGrabada->fresh()->semana);
    }

    public function test_crear_tarea_persiste_la_semana_indicada(): void
    {
        $curso = CursoVirtual::factory()->create();

        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
            'semana' => 1,
        ]);

        $this->assertSame(1, $tarea->fresh()->semana);
    }

    public function test_entregar_tarea_antes_de_la_fecha_limite_queda_como_entregado(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();

        $entrega = $this->app->make(TareaService::class)->entregar($tarea, $estudiante, 'Mi respuesta', null);

        $this->assertSame(EstadoEntregaEnum::ENTREGADO, $entrega->estado);
    }

    public function test_entregar_tarea_despues_de_la_fecha_limite_queda_como_tarde(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->subDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();

        $entrega = $this->app->make(TareaService::class)->entregar($tarea, $estudiante, null, null);

        $this->assertSame(EstadoEntregaEnum::TARDE, $entrega->estado);
    }

    public function test_calificar_una_entrega_actualiza_nota_y_estado(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();
        $service = $this->app->make(TareaService::class);
        $entrega = $service->entregar($tarea, $estudiante, null, null);

        $calificada = $service->calificar($entrega, 18.5);

        $this->assertEquals(18.5, $calificada->nota);
        $this->assertSame(EstadoEntregaEnum::CALIFICADO, $calificada->estado);
    }

    public function test_calificar_una_entrega_notifica_al_estudiante_con_usuario_vinculado(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $usuario = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $service = $this->app->make(TareaService::class);
        $entrega = $service->entregar($tarea, $estudiante, null, null);

        $service->calificar($entrega, 18.5);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $usuario->id,
            'tipo' => 'tarea_calificada',
        ]);
    }

    public function test_calificar_una_entrega_sin_usuario_vinculado_no_falla_ni_notifica(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create(['user_id' => null]);
        $service = $this->app->make(TareaService::class);
        $entrega = $service->entregar($tarea, $estudiante, null, null);

        $service->calificar($entrega, 18.5);

        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_calificar_una_entrega_de_un_estudiante_eliminado_no_falla_ni_notifica(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create(['user_id' => User::factory()]);
        $service = $this->app->make(TareaService::class);
        $entrega = $service->entregar($tarea, $estudiante, null, null);
        $estudiante->delete();

        $service->calificar($entrega, 18.5);

        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_crear_foro_persiste_la_semana_indicada(): void
    {
        $curso = CursoVirtual::factory()->create();
        $autor = User::factory()->create();

        $foro = $this->app->make(ForoService::class)->crear($curso, $autor->id, 'Dudas de la semana', null, 2);

        $this->assertSame(2, $foro->fresh()->semana);
    }

    public function test_crear_foro_sin_semana_la_deja_nula(): void
    {
        $curso = CursoVirtual::factory()->create();
        $autor = User::factory()->create();

        $foro = $this->app->make(ForoService::class)->crear($curso, $autor->id, 'Dudas generales', null);

        $this->assertNull($foro->fresh()->semana);
    }

    public function test_reentregar_actualiza_la_misma_fila_en_lugar_de_duplicarla(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();
        $service = $this->app->make(TareaService::class);

        $service->entregar($tarea, $estudiante, 'Primer intento', null);
        $service->entregar($tarea, $estudiante, 'Segundo intento', null);

        $this->assertSame(1, $tarea->entregas()->count());
        $this->assertSame('Segundo intento', $tarea->entregas()->first()->comentario);
    }

    public function test_tareas_del_estudiante_solo_incluye_las_de_sus_cursos_matriculados(): void
    {
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $service = $this->app->make(TareaService::class);
        $tareaDeMiCurso = $service->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $cursoAjeno = CursoVirtual::factory()->create();
        $service->crear($cursoAjeno, [
            'titulo' => 'Tarea ajena',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $tareas = $service->delEstudiante($estudiante);

        $this->assertCount(1, $tareas);
        $this->assertSame($tareaDeMiCurso->id, $tareas->first()->id);
    }

    public function test_tareas_del_estudiante_precarga_su_propia_entrega(): void
    {
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $service = $this->app->make(TareaService::class);
        $tarea = $service->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $service->entregar($tarea, $estudiante, 'Mi respuesta', null);

        $otroEstudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $otroEstudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $service->entregar($tarea, $otroEstudiante, 'Otra respuesta', null);

        $tareas = $service->delEstudiante($estudiante);

        $this->assertCount(1, $tareas->first()->entregas);
        $this->assertSame('Mi respuesta', $tareas->first()->entregas->first()->comentario);
    }

    public function test_tareas_del_estudiante_se_ordenan_por_fecha_limite_ascendente(): void
    {
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $service = $this->app->make(TareaService::class);
        $tareaLejana = $service->crear($curso, [
            'titulo' => 'Tarea lejana',
            'descripcion' => null,
            'fecha_limite' => now()->addWeek(),
            'puntaje_max' => 20,
        ]);
        $tareaProxima = $service->crear($curso, [
            'titulo' => 'Tarea próxima',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $tareas = $service->delEstudiante($estudiante);

        $this->assertSame($tareaProxima->id, $tareas->first()->id);
        $this->assertSame($tareaLejana->id, $tareas->last()->id);
    }
}
