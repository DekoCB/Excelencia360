<?php

namespace Tests\Feature\Migraciones;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Migraciones\Services\MigracionService;
use App\Shared\ValueObjects\Dni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigracionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): MigracionService
    {
        return $this->app->make(MigracionService::class);
    }

    private function cicloConPeriodoAbierto(array $atributos = []): Ciclo
    {
        return Ciclo::factory()->activo()->create(array_merge([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ], $atributos));
    }

    private function estudianteMatriculado(Ciclo $ciclo, Grado $grado): Estudiante
    {
        $matriculas = $this->app->make(MatriculaService::class);

        $estudiante = $matriculas->registrarEstudiante(new RegistrarEstudianteData(
            nombres: 'Ana',
            apellidos: 'García Pérez',
            dni: new Dni((string) random_int(10000000, 99999999)),
            email: uniqid('estudiante').'@example.com',
            fechaNacimiento: now()->subYears(25)->format('Y-m-d'),
            estadoCivil: null,
            direccion: null,
            celular: null,
            observaciones: null,
        ));

        $matriculas->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        return $estudiante;
    }

    public function test_migrar_crea_una_nueva_matricula_en_el_ciclo_y_grado_destino(): void
    {
        $gradoOrigen = Grado::factory()->create(['orden' => 1]);
        $gradoDestino = Grado::factory()->create(['orden' => 2]);
        $cicloOrigen = $this->cicloConPeriodoAbierto();
        $cicloDestino = $this->cicloConPeriodoAbierto();

        $estudiante = $this->estudianteMatriculado($cicloOrigen, $gradoOrigen);
        $origen = $estudiante->matriculas()->first();

        $destino = $this->service()->migrar($origen, $cicloDestino->id, $gradoDestino->id, null);

        $this->assertSame($cicloDestino->id, $destino->ciclo_id);
        $this->assertSame($gradoDestino->id, $destino->grado_id);
        $this->assertDatabaseCount('matriculas', 2);
    }

    public function test_migrar_masivo_procesa_varios_estudiantes_y_tolera_errores_por_fila(): void
    {
        $gradoOrigen = Grado::factory()->create(['orden' => 1]);
        $gradoDestino = Grado::factory()->create(['orden' => 2]);
        $cicloOrigen = $this->cicloConPeriodoAbierto();
        $cicloDestino = $this->cicloConPeriodoAbierto();

        $estudianteA = $this->estudianteMatriculado($cicloOrigen, $gradoOrigen);
        $estudianteB = $this->estudianteMatriculado($cicloOrigen, $gradoOrigen);

        // Este ya tiene una matrícula en el ciclo destino -- matricular()
        // debe rechazarla como duplicada, y migrarMasivo() debe tolerarlo
        // sin frenar al resto.
        $estudianteC = $this->estudianteMatriculado($cicloOrigen, $gradoOrigen);
        $this->app->make(MatriculaService::class)->matricular($estudianteC, new RegistrarMatriculaData($cicloDestino->id, $gradoDestino->id, null, null));

        $origenes = $this->service()->matriculasVigentes($cicloOrigen->id, null, $gradoOrigen->id);
        $this->assertCount(3, $origenes);

        $resultado = $this->service()->migrarMasivo($origenes, $cicloDestino->id, $gradoDestino->id, null);

        $this->assertSame(2, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame($estudianteC->nombreCompleto(), $resultado['errores'][0]['estudiante']);
    }

    public function test_matriculas_vigentes_filtra_por_ciclo_programa_de_estudio_y_grado(): void
    {
        $programaA = ProgramaEstudio::factory()->create();
        $programaB = ProgramaEstudio::factory()->create();
        $gradoProgramaA = Grado::factory()->create(['programa_estudio_id' => $programaA->id, 'orden' => 1]);
        $gradoProgramaB = Grado::factory()->create(['programa_estudio_id' => $programaB->id, 'orden' => 1]);
        $ciclo = $this->cicloConPeriodoAbierto();

        $this->estudianteMatriculado($ciclo, $gradoProgramaA);
        $this->estudianteMatriculado($ciclo, $gradoProgramaB);

        $this->assertCount(1, $this->service()->matriculasVigentes($ciclo->id, $programaA->id, null));
        $this->assertCount(1, $this->service()->matriculasVigentes($ciclo->id, $programaB->id, null));
        $this->assertCount(2, $this->service()->matriculasVigentes($ciclo->id, null, null));
        $this->assertCount(1, $this->service()->matriculasVigentes($ciclo->id, null, $gradoProgramaA->id));
    }

    public function test_grado_siguiente_devuelve_el_de_orden_inmediato_superior(): void
    {
        $programa = ProgramaEstudio::factory()->create();
        $grado1 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 1]);
        $grado2 = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'orden' => 2]);

        $this->assertSame($grado2->id, $this->service()->gradoSiguiente($grado1)->id);
        $this->assertNull($this->service()->gradoSiguiente($grado2));
    }

    public function test_grado_siguiente_no_cruza_a_otro_programa_de_estudio(): void
    {
        $programaA = ProgramaEstudio::factory()->create();
        $programaB = ProgramaEstudio::factory()->create();
        $grado1DeA = Grado::factory()->create(['programa_estudio_id' => $programaA->id, 'orden' => 1]);
        // El programa A no tiene un orden 2 propio, pero el B sí -- si
        // gradoSiguiente() no filtrara por programa, devolvería este.
        Grado::factory()->create(['programa_estudio_id' => $programaB->id, 'orden' => 2]);

        $this->assertNull($this->service()->gradoSiguiente($grado1DeA));
    }
}
