<?php

namespace Tests\Feature\Migraciones;

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CicloService;
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
        $ciclo = Ciclo::factory()->activo()->create(array_merge([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ], $atributos));

        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);

        return $ciclo;
    }

    private function estudianteMatriculado(Ciclo $ciclo, Grado $grado): Estudiante
    {
        $matriculas = $this->app->make(MatriculaService::class);

        $estudiante = $matriculas->registrarEstudiante(new RegistrarEstudianteData(
            nombres: 'Ana',
            apellidos: 'García Pérez',
            dni: new Dni((string) random_int(10000000, 99999999)),
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

        $origenes = $this->service()->matriculasVigentes(null, $cicloOrigen->id, null, $gradoOrigen->id);
        $this->assertCount(3, $origenes);

        $resultado = $this->service()->migrarMasivo($origenes, $cicloDestino->id, $gradoDestino->id, null);

        $this->assertSame(2, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame($estudianteC->nombreCompleto(), $resultado['errores'][0]['estudiante']);
    }

    public function test_matriculas_vigentes_filtra_por_ciclo_seccion_y_grado(): void
    {
        $gradoSeccionA = Grado::factory()->create(['orden' => 1]);
        $gradoSeccionB = Grado::factory()->create(['orden' => 3]);
        $ciclo = $this->cicloConPeriodoAbierto();

        $this->estudianteMatriculado($ciclo, $gradoSeccionA);
        $this->estudianteMatriculado($ciclo, $gradoSeccionB);

        $this->assertCount(1, $this->service()->matriculasVigentes(null, $ciclo->id, 'A', null));
        $this->assertCount(1, $this->service()->matriculasVigentes(null, $ciclo->id, 'B', null));
        $this->assertCount(2, $this->service()->matriculasVigentes(null, $ciclo->id, null, null));
        $this->assertCount(1, $this->service()->matriculasVigentes(null, $ciclo->id, null, $gradoSeccionA->id));
    }

    public function test_matriculas_vigentes_filtra_por_modalidad(): void
    {
        $grado = Grado::factory()->create(['orden' => 1]);
        $cicloSeisMeses = $this->cicloConPeriodoAbierto();
        $cicloAnual = $this->cicloConPeriodoAbierto(['modalidad' => ModalidadCicloEnum::ANUAL, 'tipo' => null]);

        $this->estudianteMatriculado($cicloSeisMeses, $grado);
        $this->estudianteMatriculado($cicloAnual, $grado);

        $this->assertCount(1, $this->service()->matriculasVigentes(ModalidadCicloEnum::SEIS_MESES, null, null, $grado->id));
        $this->assertCount(1, $this->service()->matriculasVigentes(ModalidadCicloEnum::ANUAL, null, null, $grado->id));
        $this->assertCount(2, $this->service()->matriculasVigentes(null, null, null, $grado->id));
    }

    public function test_ciclo_anual_vigente_devuelve_el_mas_reciente(): void
    {
        Ciclo::factory()->anual()->create(['anio' => 2025, 'fecha_inicio' => '2025-03-01', 'fecha_fin' => '2025-12-20']);
        $masReciente = Ciclo::factory()->anual()->create(['anio' => 2027, 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-12-20']);
        Ciclo::factory()->anual()->create(['anio' => 2026, 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-12-20']);

        $this->assertSame($masReciente->id, $this->service()->cicloAnualVigente()->id);
    }

    public function test_grado_siguiente_devuelve_el_de_orden_inmediato_superior(): void
    {
        $grado1 = Grado::factory()->create(['orden' => 1]);
        $grado2 = Grado::factory()->create(['orden' => 2]);

        $this->assertSame($grado2->id, $this->service()->gradoSiguiente($grado1)->id);
        $this->assertNull($this->service()->gradoSiguiente($grado2));
    }

    public function test_ciclo_destino_sugerido_usa_siguiente_ciclo_para_seis_meses(): void
    {
        $actual = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_1, 'anio' => 2026]);
        $siguiente = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_3, 'anio' => 2026]);

        $sugerido = $this->service()->cicloDestinoSugerido($actual, $this->app->make(CicloService::class));

        $this->assertSame($siguiente->id, $sugerido->id);
    }

    public function test_ciclo_destino_sugerido_busca_el_ciclo_anual_del_anio_siguiente(): void
    {
        $actual = Ciclo::factory()->anual()->create(['anio' => 2026]);
        $siguiente = Ciclo::factory()->anual()->create(['anio' => 2027]);

        $sugerido = $this->service()->cicloDestinoSugerido($actual, $this->app->make(CicloService::class));

        $this->assertSame($siguiente->id, $sugerido->id);
    }
}
