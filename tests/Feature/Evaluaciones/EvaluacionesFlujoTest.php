<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EvaluacionesFlujoTest extends TestCase
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

    private function crear(CursoVirtual $curso, string $nombre, string $fecha): Evaluacion
    {
        return $this->app->make(EvaluacionService::class)->crear($curso, $nombre, $fecha, TipoEvaluacionEnum::FISICO);
    }

    public function test_el_docente_crea_una_evaluacion_desde_la_pestana_de_cursos_virtuales(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('evaluacionNombre', 'Evaluación mensual — julio')
            ->set('evaluacionFecha', '2026-07-15')
            ->set('evaluacionTipo', 'fisico')
            ->call('crearEvaluacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'curso_virtual_id' => $curso->id,
            'nombre' => 'Evaluación mensual — julio',
            'tipo' => 'fisico',
            'estado' => 'borrador',
        ]);
    }

    public function test_el_docente_registra_notas_de_una_evaluacion_fisica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $evaluacion = $this->crear($curso, 'Evaluación mensual', '2026-07-15');

        $this->actingAs($docente);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("notas.{$estudiante->id}", '17.5')
            ->set("observaciones.{$estudiante->id}", 'Buen desempeño')
            ->call('guardarNotas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 17.5,
            'observaciones' => 'Buen desempeño',
        ]);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     */
    private function archivoExcel(array $encabezados, array $filas): UploadedFile
    {
        $hoja = new Spreadsheet;
        $hoja->getActiveSheet()->fromArray($encabezados, null, 'A1');
        $hoja->getActiveSheet()->fromArray($filas, null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'notas_test_').'.xlsx';
        (new Xlsx($hoja))->save($ruta);

        $archivo = UploadedFile::fake()->createWithContent('notas.xlsx', file_get_contents($ruta));
        unlink($ruta);

        return $archivo;
    }

    public function test_el_docente_importa_notas_desde_un_archivo(): void
    {
        Storage::fake('local');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $estudiante = Estudiante::factory()->create(['dni' => '87654321']);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $evaluacion = $this->crear($curso, 'Evaluación por Google Forms', '2026-07-15');

        $this->actingAs($docente);

        $archivo = $this->archivoExcel(['dni', 'nota', 'observaciones'], [
            ['87654321', '18.5', 'Importado'],
        ]);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('archivoNotas', $archivo)
            ->call('importarNotas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 18.5,
            'observaciones' => 'Importado',
        ]);
    }

    public function test_un_docente_dueno_de_otro_curso_no_puede_importar_notas(): void
    {
        Storage::fake('local');

        $docenteOwner = User::factory()->create();
        $docenteOwner->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docenteOwner);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($otroDocente);

        $archivo = $this->archivoExcel(['dni', 'nota'], [['12345678', '15']]);

        rescue(fn () => Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('archivoNotas', $archivo)
            ->call('importarNotas'), report: false);

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_no_permite_registrar_una_nota_fuera_del_rango_0_20(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($docente);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("notas.{$estudiante->id}", '25')
            ->call('guardarNotas')
            ->assertHasErrors(["notas.{$estudiante->id}"]);
    }

    public function test_no_permite_un_enlace_externo_invalido(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación mensual', '2026-07-15');

        $this->actingAs($docente);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('enlaceEditar', 'no-es-una-url')
            ->call('actualizarEnlace')
            ->assertHasErrors(['enlaceEditar']);
    }

    public function test_el_docente_puede_editar_el_enlace_de_una_evaluacion_existente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación mensual', '2026-07-15');

        $this->actingAs($docente);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('enlaceEditar', 'https://forms.test/actualizado')
            ->call('actualizarEnlace')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'id' => $evaluacion->id,
            'enlace_externo' => 'https://forms.test/actualizado',
        ]);
    }

    public function test_un_estudiante_matriculado_no_ve_el_enlace_de_una_evaluacion_en_borrador(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $evaluacion = $this->crear($curso, 'Evaluación mensual', '2026-07-15');
        $this->app->make(EvaluacionService::class)->actualizarEnlace($evaluacion, 'https://forms.test/examen');

        $this->actingAs($usuario);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->assertDontSee('https://forms.test/examen');
    }

    public function test_un_estudiante_matriculado_ve_el_enlace_recien_cuando_se_publica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $service = $this->app->make(EvaluacionService::class);
        $evaluacion = $this->crear($curso, 'Evaluación mensual', now()->format('Y-m-d'));
        $service->actualizarEnlace($evaluacion, 'https://forms.test/examen');
        $service->publicar($evaluacion);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion->refresh()])
            ->assertSee('https://forms.test/examen');
    }

    public function test_el_docente_ve_todas_las_evaluaciones_del_curso_en_la_pestana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->crear($curso, 'Evaluación del 15', '2026-07-15');
        $this->crear($curso, 'Evaluación del 10', '2026-07-10');

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tab', 'evaluaciones')
            ->assertSee('Evaluación del 15')
            ->assertSee('Evaluación del 10');
    }

    public function test_un_estudiante_solo_ve_las_evaluaciones_publicadas_en_la_pestana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $curso->horario->grado_id,
            'ciclo_id' => $curso->horario->ciclo_id,
        ]);

        $service = $this->app->make(EvaluacionService::class);
        $publicada = $this->crear($curso, 'Evaluación publicada', '2026-07-15');
        $service->publicar($publicada);
        $this->crear($curso, 'Evaluación en borrador', '2026-07-16');

        $this->actingAs($usuario);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tab', 'evaluaciones')
            ->assertSee('Evaluación publicada')
            ->assertDontSee('Evaluación en borrador');
    }
}
