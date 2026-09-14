<?php

namespace Tests\Feature\Vacaciones;

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Vacaciones\Services\VacacionService;
use App\Shared\ValueObjects\Dni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacacionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): VacacionService
    {
        return $this->app->make(VacacionService::class);
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

    private function estudianteMatriculado(Ciclo $ciclo): Estudiante
    {
        $matriculas = $this->app->make(MatriculaService::class);
        $grado = Grado::factory()->create();

        $estudiante = $matriculas->registrarEstudiante(new RegistrarEstudianteData(
            nombres: 'Fiorella',
            apellidos: 'Aquino Ramírez',
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

    public function test_activar_rechaza_un_estudiante_que_no_esta_en_siagie_anual(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto(['tipo' => TipoCicloEnum::GRUPO_1]);
        $estudiante = $this->estudianteMatriculado($ciclo);

        $this->expectException(ValidationException::class);

        $this->service()->activar($estudiante, now()->format('Y-m-d'), null);
    }

    public function test_activar_calcula_la_fecha_fin_a_dos_meses(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto(['modalidad' => ModalidadCicloEnum::ANUAL, 'tipo' => null]);
        $estudiante = $this->estudianteMatriculado($ciclo);

        $vacacion = $this->service()->activar($estudiante, '2026-06-01', null);

        $this->assertSame('2026-06-01', $vacacion->fecha_inicio->format('Y-m-d'));
        $this->assertSame('2026-08-01', $vacacion->fecha_fin->format('Y-m-d'));
    }

    public function test_vigentes_y_historial_particionan_por_fecha_fin(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto(['modalidad' => ModalidadCicloEnum::ANUAL, 'tipo' => null]);
        $enVacaciones = $this->estudianteMatriculado($ciclo);
        $yaTermino = $this->estudianteMatriculado($ciclo);

        $this->service()->activar($enVacaciones, now()->subDays(10)->format('Y-m-d'), null);
        $this->service()->activar($yaTermino, now()->subMonths(4)->format('Y-m-d'), null);

        $vigentes = $this->service()->vigentes();
        $historial = $this->service()->historial();

        $this->assertCount(1, $vigentes);
        $this->assertSame($enVacaciones->id, $vigentes->first()->estudiante_id);
        $this->assertCount(1, $historial);
        $this->assertSame($yaTermino->id, $historial->first()->estudiante_id);
    }
}
