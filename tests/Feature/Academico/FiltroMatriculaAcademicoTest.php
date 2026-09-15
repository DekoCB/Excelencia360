<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Support\FiltroMatriculaAcademico;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extraído el 2026-09-15 de App\Modules\Reportes\Services\ReporteService
 * (ver docs/BITACORA.md, fase 11) para reutilizarlo en la búsqueda
 * avanzada de estudiantes. Los tests preexistentes de
 * tests/Feature/Reportes/ReporteServiceTest.php ya cubren el
 * comportamiento heredado (ciclo/grado/curso/franja/siagie); este
 * archivo solo cubre lo nuevo: el filtro por docente.
 */
class FiltroMatriculaAcademicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtrar_por_docente_sin_curso_incluye_a_todo_el_grado_y_ciclo(): void
    {
        $docenteX = User::factory()->create();
        $docenteY = User::factory()->create();

        // Mismo grado+ciclo, dos cursos distintos con docentes distintos:
        // un estudiante de ese grado+ciclo lleva ambos cursos (sin
        // paralelos, la matrícula no necesita asignación explícita).
        $horarioA = Horario::factory()->create(['docente_id' => $docenteX->id]);
        Horario::factory()->create([
            'docente_id' => $docenteY->id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);

        Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);

        $resultado = FiltroMatriculaAcademico::filtrarMatriculas(
            Matricula::query(),
            cicloId: null,
            gradoId: null,
            cursoId: null,
            franja: null,
            docenteId: $docenteX->id,
        )->get();

        // Ambos estudiantes llevan el curso de docenteX, aunque uno de
        // ellos también lleve el de docenteY -- sin paralelos, no hace
        // falta asignación explícita para que cuenten.
        $this->assertCount(2, $resultado);
    }

    public function test_filtrar_por_docente_y_curso_con_paralelos_solo_incluye_a_los_asignados_a_ese_docente(): void
    {
        $docenteX = User::factory()->create();
        $docenteY = User::factory()->create();
        $curso = Curso::factory()->create();

        $horarioX = Horario::factory()->create(['curso_id' => $curso->id, 'docente_id' => $docenteX->id]);
        $horarioY = Horario::factory()->create([
            'curso_id' => $curso->id,
            'docente_id' => $docenteY->id,
            'grado_id' => $horarioX->grado_id,
            'ciclo_id' => $horarioX->ciclo_id,
        ]);

        $matriculaDeX = Matricula::factory()->create(['grado_id' => $horarioX->grado_id, 'ciclo_id' => $horarioX->ciclo_id]);
        $matriculaDeX->horarios()->attach($horarioX->id);

        $matriculaDeY = Matricula::factory()->create(['grado_id' => $horarioX->grado_id, 'ciclo_id' => $horarioX->ciclo_id]);
        $matriculaDeY->horarios()->attach($horarioY->id);

        $resultado = FiltroMatriculaAcademico::filtrarMatriculas(
            Matricula::query(),
            cicloId: $horarioX->ciclo_id,
            gradoId: $horarioX->grado_id,
            cursoId: $curso->id,
            franja: null,
            docenteId: $docenteX->id,
        )->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($matriculaDeX->id, $resultado->first()->id);
    }

    public function test_sin_filtros_no_restringe_nada(): void
    {
        $this->assertTrue(FiltroMatriculaAcademico::sinFiltros(null, null, null, null));
        $this->assertFalse(FiltroMatriculaAcademico::sinFiltros(null, null, null, null, null, docenteId: 5));
    }
}
