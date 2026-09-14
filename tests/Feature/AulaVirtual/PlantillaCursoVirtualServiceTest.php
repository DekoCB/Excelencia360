<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Services\ClaseGrabadaService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\PlantillaCursoVirtualService;
use App\Modules\AulaVirtual\Services\TareaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlantillaCursoVirtualServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlantillaCursoVirtualService
    {
        return $this->app->make(PlantillaCursoVirtualService::class);
    }

    public function test_guardar_copia_materiales_clases_tareas_y_foros_con_su_semana(): void
    {
        $curso = CursoVirtual::factory()->create();
        $autor = User::factory()->create();

        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video', 'https://ejemplo.test', null, 1);
        $this->app->make(ClaseGrabadaService::class)->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase', 'https://youtube.test', null, 2);
        $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
            'semana' => 1,
        ]);
        $this->app->make(ForoService::class)->crear($curso, $autor->id, 'Dudas', null, 1);

        $plantilla = $this->service()->guardarDesdeCursoVirtual($curso, 'Plantilla estándar', $autor);

        $this->assertSame('Plantilla estándar', $plantilla->nombre);
        $this->assertSame($autor->id, $plantilla->creado_por);
        $this->assertSame(1, $plantilla->materiales()->count());
        $this->assertSame(1, $plantilla->clasesGrabadas()->count());
        $this->assertSame(1, $plantilla->tareas()->count());
        $this->assertSame(1, $plantilla->foros()->count());
        $this->assertSame(1, $plantilla->materiales()->first()->semana);
    }

    public function test_guardar_copia_el_archivo_del_material_y_de_la_clase_grabada(): void
    {
        Storage::fake('public');
        $curso = CursoVirtual::factory()->create();
        $autor = User::factory()->create();

        $this->app->make(MaterialService::class)->crear(
            $curso,
            TipoMaterialEnum::PDF,
            'Separata',
            null,
            UploadedFile::fake()->create('separata.pdf', 500, 'application/pdf'),
        );
        $this->app->make(ClaseGrabadaService::class)->crear(
            $curso,
            TipoClaseGrabadaEnum::ARCHIVO,
            'Clase grabada',
            null,
            UploadedFile::fake()->create('clase.mp4', 5000, 'video/mp4'),
        );

        $plantilla = $this->service()->guardarDesdeCursoVirtual($curso, 'Plantilla', $autor);

        $this->assertNotNull($plantilla->materiales()->first()->getFirstMedia('archivo'));
        $this->assertNotNull($plantilla->clasesGrabadas()->first()->getFirstMedia('video'));
    }

    public function test_aplicar_agrega_el_contenido_sin_borrar_lo_existente(): void
    {
        $cursoOrigen = CursoVirtual::factory()->create();
        $autor = User::factory()->create();
        $this->app->make(MaterialService::class)->crear($cursoOrigen, TipoMaterialEnum::ENLACE, 'Video', 'https://ejemplo.test', null, 1);
        $plantilla = $this->service()->guardarDesdeCursoVirtual($cursoOrigen, 'Plantilla', $autor);

        $cursoDestino = CursoVirtual::factory()->create();
        $this->app->make(MaterialService::class)->crear($cursoDestino, TipoMaterialEnum::ENLACE, 'Ya existente', 'https://ejemplo.test/2', null);

        $aplicados = $this->service()->aplicar($plantilla, $cursoDestino, $autor);

        $this->assertSame(1, $aplicados);
        $this->assertSame(2, $cursoDestino->materiales()->count());
    }

    public function test_aplicar_recalcula_la_fecha_limite_de_la_tarea_segun_el_ciclo_destino(): void
    {
        $cursoOrigen = CursoVirtual::factory()->create();
        $autor = User::factory()->create();
        $this->app->make(TareaService::class)->crear($cursoOrigen, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
            'semana' => 2,
        ]);
        $plantilla = $this->service()->guardarDesdeCursoVirtual($cursoOrigen, 'Plantilla', $autor);

        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2027-01-04']);
        $horarioDestino = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $cursoDestino = CursoVirtual::factory()->create(['horario_id' => $horarioDestino->id]);

        $this->service()->aplicar($plantilla, $cursoDestino, $autor);

        $tareaAplicada = $cursoDestino->tareas()->first();
        $this->assertSame('2027-01-18', $tareaAplicada->fecha_limite->format('Y-m-d'));
    }

    public function test_eliminar_borra_la_plantilla_y_sus_hijos(): void
    {
        $curso = CursoVirtual::factory()->create();
        $autor = User::factory()->create();
        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video', 'https://ejemplo.test', null);
        $plantilla = $this->service()->guardarDesdeCursoVirtual($curso, 'Plantilla', $autor);

        $this->service()->eliminar($plantilla);

        $this->assertDatabaseMissing('plantillas_curso_virtual', ['id' => $plantilla->id]);
        $this->assertDatabaseMissing('plantilla_materiales', ['plantilla_id' => $plantilla->id]);
    }

    public function test_listar_por_curso_solo_incluye_plantillas_de_ese_curso(): void
    {
        $cursoUno = CursoVirtual::factory()->create();
        $cursoDos = CursoVirtual::factory()->create();
        $autor = User::factory()->create();

        $plantillaUno = $this->service()->guardarDesdeCursoVirtual($cursoUno, 'Plantilla 1', $autor);
        $this->service()->guardarDesdeCursoVirtual($cursoDos, 'Plantilla 2', $autor);

        $plantillas = $this->service()->listarPorCurso($cursoUno->horario->curso);

        $this->assertCount(1, $plantillas);
        $this->assertSame($plantillaUno->id, $plantillas->first()->id);
    }
}
