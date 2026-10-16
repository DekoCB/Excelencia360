<?php

namespace Tests\Feature\Matricula;

use App\Modules\Academico\Models\Curso;
use App\Modules\Matricula\Enums\EstadoMatriculaCursoCortoEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MatriculaCursoCortoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MatriculaService
    {
        return app(MatriculaService::class);
    }

    public function test_matricular_curso_corto_crea_el_registro_activo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->capacitacion()->create(['nombre' => 'Ofimática Básica']);

        $cursoCorto = $this->service()->matricularCursoCorto($estudiante, [
            'curso_id' => $curso->id,
            'fecha_inicio' => '2026-11-01',
            'observaciones' => 'Pago acordado en efectivo.',
            'registrado_por' => null,
        ]);

        $this->assertInstanceOf(MatriculaCursoCorto::class, $cursoCorto);
        $this->assertSame($estudiante->id, $cursoCorto->estudiante_id);
        $this->assertSame($curso->id, $cursoCorto->curso_id);
        $this->assertSame('2026-11-01', $cursoCorto->fecha_inicio->format('Y-m-d'));
        $this->assertNull($cursoCorto->fecha_fin);
        $this->assertSame(EstadoMatriculaCursoCortoEnum::ACTIVO, $cursoCorto->estado);
        $this->assertSame('Pago acordado en efectivo.', $cursoCorto->observaciones);
    }

    public function test_matricular_curso_corto_rechaza_un_curso_que_no_es_de_capacitacion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->matricularCursoCorto($estudiante, [
            'curso_id' => $curso->id,
            'fecha_inicio' => '2026-11-01',
            'observaciones' => null,
            'registrado_por' => null,
        ]);
    }

    public function test_un_estudiante_puede_tener_varios_cursos_cortos_a_la_vez(): void
    {
        $estudiante = Estudiante::factory()->create();
        $cursoUno = Curso::factory()->capacitacion()->create();
        $cursoDos = Curso::factory()->capacitacion()->create();

        $this->service()->matricularCursoCorto($estudiante, [
            'curso_id' => $cursoUno->id, 'fecha_inicio' => '2026-10-01', 'observaciones' => null, 'registrado_por' => null,
        ]);
        $this->service()->matricularCursoCorto($estudiante, [
            'curso_id' => $cursoDos->id, 'fecha_inicio' => '2026-10-01', 'observaciones' => null, 'registrado_por' => null,
        ]);

        $this->assertSame(2, $estudiante->cursosCortos()->count());
    }

    public function test_finalizar_curso_corto_como_concluido_deja_fecha_fin_en_hoy(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $cursoCorto = MatriculaCursoCorto::factory()->create();

        $this->service()->finalizarCursoCorto($cursoCorto, EstadoMatriculaCursoCortoEnum::CONCLUIDO);

        $cursoCorto->refresh();
        $this->assertSame(EstadoMatriculaCursoCortoEnum::CONCLUIDO, $cursoCorto->estado);
        $this->assertSame(now()->format('Y-m-d'), $cursoCorto->fecha_fin->format('Y-m-d'));
    }

    public function test_finalizar_curso_corto_como_retirado_no_rellena_fecha_fin_sola(): void
    {
        $cursoCorto = MatriculaCursoCorto::factory()->create();

        $this->service()->finalizarCursoCorto($cursoCorto, EstadoMatriculaCursoCortoEnum::RETIRADO);

        $cursoCorto->refresh();
        $this->assertSame(EstadoMatriculaCursoCortoEnum::RETIRADO, $cursoCorto->estado);
        $this->assertNull($cursoCorto->fecha_fin);
    }
}
