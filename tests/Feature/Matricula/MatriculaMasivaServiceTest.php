<?php

namespace Tests\Feature\Matricula;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
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

        $ciclo->update([
            'fecha_inicio_matricula' => now()->subDays(10),
            'fecha_fin_matricula' => now()->addDays(10),
            'estado_matricula' => 'abierto',
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

    public function test_registra_un_estudiante_sin_fecha_de_nacimiento_como_no_menor_de_edad_y_sin_exigir_apoderado(): void
    {
        $resultado = $this->service()->registrarEstudiantesDesdeFilas($this->filas([
            [
                'nombres' => 'Carlos', 'apellidos' => 'Gómez Luna', 'dni' => '76543210',
            ],
        ]));

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('estudiantes', [
            'dni' => '76543210',
            'fecha_nacimiento' => null,
            'es_menor_edad' => false,
        ]);
        $this->assertDatabaseCount('apoderados', 0);
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

    public function test_matricula_masivamente_estudiantes_existentes_por_dni_carrera_y_semestre(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        $programa = ProgramaEstudio::factory()->create(['nombre' => 'Administración de Empresas']);
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 1']);
        $estudiante = Estudiante::factory()->create(['dni' => '44556677', 'es_menor_edad' => false]);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '44556677', 'carrera' => 'Administración de Empresas', 'semestre' => 'Semestre 1'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
        ]);
    }

    /**
     * Dos carreras pueden tener un semestre con el mismo nombre -- sin la
     * columna "carrera", buscar el grado solo por nombre podía chocar con
     * el de la otra carrera.
     */
    public function test_matricula_masiva_no_confunde_semestres_del_mismo_nombre_en_carreras_distintas(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        $programaA = ProgramaEstudio::factory()->create(['nombre' => 'Administración de Empresas']);
        $programaB = ProgramaEstudio::factory()->create(['nombre' => 'Contabilidad']);
        Grado::factory()->create(['programa_estudio_id' => $programaA->id, 'nombre' => 'Semestre 1']);
        $gradoB = Grado::factory()->create(['programa_estudio_id' => $programaB->id, 'nombre' => 'Semestre 1']);
        $estudiante = Estudiante::factory()->create(['dni' => '44556677', 'es_menor_edad' => false]);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '44556677', 'carrera' => 'Contabilidad', 'semestre' => 'Semestre 1'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'grado_id' => $gradoB->id]);
    }

    public function test_matricula_masiva_con_columna_curso_matricula_solo_en_ese_curso(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        $programa = ProgramaEstudio::factory()->create();
        $grado = Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 1']);
        $cursoElegido = Curso::factory()->hasAttached($grado)->create(['nombre' => 'Contabilidad General']);
        $otroCurso = Curso::factory()->hasAttached($grado)->create(['nombre' => 'Matemática Financiera']);
        $horarioElegido = Horario::factory()->create(['curso_id' => $cursoElegido->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        Horario::factory()->create(['curso_id' => $otroCurso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create(['dni' => '44556677', 'es_menor_edad' => false]);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '44556677', 'carrera' => $programa->nombre, 'semestre' => 'Semestre 1', 'curso' => 'Contabilidad General'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $matricula = Matricula::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame([$horarioElegido->id], $matricula->horarios()->pluck('horarios.id')->all());
    }

    public function test_matricula_masiva_reporta_un_dni_inexistente_como_error(): void
    {
        $ciclo = $this->cicloConPeriodoAbierto();
        $programa = ProgramaEstudio::factory()->create();
        Grado::factory()->create(['programa_estudio_id' => $programa->id, 'nombre' => 'Semestre 1']);

        $resultado = $this->service()->matricularDesdeFilas($ciclo->id, $this->filas([
            ['dni' => '00000000', 'carrera' => $programa->nombre, 'semestre' => 'Semestre 1'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertStringContainsString('00000000', $resultado['errores'][0]['mensaje']);
    }

    public function test_matricula_masiva_de_curso_corto_por_dni_y_curso(): void
    {
        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica']);
        $estudiante = Estudiante::factory()->create(['dni' => '44556677']);

        $resultado = $this->service()->matricularCursoCortoDesdeFilas($this->filas([
            ['dni' => '44556677', 'curso' => 'Ofimática Básica'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertDatabaseHas('matriculas_curso_corto', [
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'estado' => 'activo',
        ]);
    }

    public function test_matricula_masiva_de_curso_corto_reporta_curso_inexistente(): void
    {
        Estudiante::factory()->create(['dni' => '44556677']);

        $resultado = $this->service()->matricularCursoCortoDesdeFilas($this->filas([
            ['dni' => '44556677', 'curso' => 'Curso Que No Existe'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertStringContainsString('Curso Que No Existe', $resultado['errores'][0]['mensaje']);
    }
}
