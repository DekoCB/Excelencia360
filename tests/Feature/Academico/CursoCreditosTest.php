<?php

namespace Tests\Feature\Academico;

use App\Modules\Academico\Enums\TipoCursoEnum;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CursoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoCreditosTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CursoService
    {
        return $this->app->make(CursoService::class);
    }

    public function test_crear_curso_guarda_los_creditos(): void
    {
        $grado = Grado::factory()->create();

        $curso = $this->service()->crear([
            'nombre' => 'Matemática',
            'codigo' => 'MAT-1',
            'tipo' => TipoCursoEnum::ACADEMICO,
            'grado_ids' => [$grado->id],
            'franjas_permitidas' => null,
            'horas' => 4,
            'creditos' => 4,
        ]);

        $this->assertSame(4, $curso->creditos);
    }

    public function test_crear_curso_sin_creditos_queda_nulo(): void
    {
        $grado = Grado::factory()->create();

        $curso = $this->service()->crear([
            'nombre' => 'Comunicación',
            'codigo' => 'COM-1',
            'tipo' => TipoCursoEnum::ACADEMICO,
            'grado_ids' => [$grado->id],
            'franjas_permitidas' => null,
            'horas' => 4,
            'creditos' => null,
        ]);

        $this->assertNull($curso->creditos);
    }

    public function test_actualizar_curso_cambia_los_creditos(): void
    {
        $grado = Grado::factory()->create();
        $curso = Curso::factory()->create(['creditos' => 2]);

        $this->service()->actualizar($curso, [
            'nombre' => $curso->nombre,
            'codigo' => $curso->codigo,
            'grado_ids' => [$grado->id],
            'franjas_permitidas' => null,
            'horas' => $curso->horas,
            'creditos' => 5,
            'activo' => true,
        ]);

        $this->assertSame(5, $curso->refresh()->creditos);
    }
}
