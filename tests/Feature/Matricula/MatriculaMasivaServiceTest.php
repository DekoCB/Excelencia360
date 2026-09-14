<?php

namespace Tests\Feature\Matricula;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MatriculaMasivaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): MatriculaService
    {
        return $this->app->make(MatriculaService::class);
    }

    private function cicloConPeriodoAbierto(): Ciclo
    {
        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);

        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);

        return $ciclo;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return Collection<int, Collection<string, mixed>>
     */
    private function filas(array $filas): Collection
    {
        return collect($filas)->map(fn (array $fila) => collect($fila));
    }

    public function test_registra_un_estudiante_mayor_de_edad_desde_una_fila(): void
    {
        $resultado = $this->service()->registrarEstudiantesDesdeFilas($this->filas([
            [
                'nombres' => 'Carlos', 'apellidos' => 'Gómez Luna', 'dni' => '76543210',
                'fecha_nacimiento' => '20/06/1990', 'estado_civil' => 'soltero',
                'celular' => '923456789', 'email' => 'carlos@example.com',
            ],
        ]));

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('estudiantes', ['dni' => '76543210', 'es_menor_edad' => false]);
    }

    public function test_registra_un_menor_de_edad_junto_con_su_apoderado(): void
    {
        $resultado = $this->service()->registrarEstudiantesDesdeFilas($this->filas([
            [
                'nombres' => 'Juana', 'apellidos' => 'Pérez Ríos', 'dni' => '87654321',
                'fecha_nacimiento' => now()->subYears(15)->format('d/m/Y'),
                'apoderado_nombres' => 'Rosa Ríos Gómez', 'apoderado_dni' => '12345678',
                'apoderado_celular' => '912345678', 'apoderado_parentesco' => 'Madre',
            ],
        ]));

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);

        $estudiante = Estudiante::query()->where('dni', '87654321')->firstOrFail();
        $this->assertTrue($estudiante->es_menor_edad);
        $this->assertSame('Rosa Ríos Gómez', $estudiante->apoderado->nombres);
    }

    public function test_un_menor_sin_datos_de_apoderado_se_reporta_como_error_y_no_deja_registro_huerfano(): void
    {
        $resultado = $this->service()->registrarEstudiantesDesdeFilas($this->filas([
            [
                'nombres' => 'Luis', 'apellidos' => 'Torres Vidal', 'dni' => '11223344',
                'fecha_nacimiento' => now()->subYears(15)->format('d/m/Y'),
            ],
        ]));

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
        $this->assertDatabaseMissing('estudiantes', ['dni' => '11223344']);
    }

    public function test_una_fila_con_dni_duplicado_no_afecta_a_las_demas_filas_validas(): void
    {
        Estudiante::factory()->create(['dni' => '99999999']);

        $resultado = $this->service()->registrarEstudiantesDesdeFilas($this->filas([
            [
                'nombres' => 'Repetido', 'apellidos' => 'Duplicado', 'dni' => '99999999',
                'fecha_nacimiento' => '01/01/1995',
            ],
            [
                'nombres' => 'Válido', 'apellidos' => 'Correcto', 'dni' => '55667788',
                'fecha_nacimiento' => '01/01/1995',
            ],
        ]));

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
        $this->assertDatabaseHas('estudiantes', ['dni' => '55667788']);
    }

    public function test_matricula_masivamente_estudiantes_existentes_por_dni_y_grado(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['nombre' => '1ro de Secundaria']);
        $estudiante = Estudiante::factory()->create(['dni' => '44556677', 'es_menor_edad' => false]);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '44556677', 'grado' => '1ro de Secundaria'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
        ]);
    }

    public function test_matricula_masiva_reporta_un_dni_inexistente_como_error(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        Grado::factory()->create(['nombre' => '1ro de Secundaria']);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '00000000', 'grado' => '1ro de Secundaria'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertStringContainsString('00000000', $resultado['errores'][0]['mensaje']);
    }
}
