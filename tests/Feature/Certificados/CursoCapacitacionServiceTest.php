<?php

namespace Tests\Feature\Certificados;

use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Services\CursoCapacitacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoCapacitacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CursoCapacitacionService
    {
        return app(CursoCapacitacionService::class);
    }

    public function test_crear_persiste_un_curso_de_capacitacion(): void
    {
        $curso = $this->service()->crear('Ofimática Nivel Avanzado', 130, 'R.D.R. N°2182-2023-DREP');

        $this->assertDatabaseHas('cursos_capacitacion', [
            'id' => $curso->id,
            'nombre' => 'Ofimática Nivel Avanzado',
            'horas_lectivas' => 130,
            'documento_autorizacion' => 'R.D.R. N°2182-2023-DREP',
        ]);
    }

    public function test_crear_sin_documento_de_autorizacion_lo_deja_nulo(): void
    {
        $curso = $this->service()->crear('Curso sin resolución', 40, null);

        $this->assertNull($curso->documento_autorizacion);
    }

    public function test_actualizar_modifica_un_curso_existente(): void
    {
        $curso = CursoCapacitacion::factory()->create(['nombre' => 'Nombre original']);

        $this->service()->actualizar($curso, 'Nombre actualizado', 200, 'R.D. N.° 999-2026');

        $this->assertDatabaseHas('cursos_capacitacion', [
            'id' => $curso->id,
            'nombre' => 'Nombre actualizado',
            'horas_lectivas' => 200,
        ]);
    }

    public function test_eliminar_borra_el_curso(): void
    {
        $curso = CursoCapacitacion::factory()->create();

        $this->service()->eliminar($curso);

        $this->assertDatabaseMissing('cursos_capacitacion', ['id' => $curso->id]);
    }

    public function test_todos_ordena_por_nombre(): void
    {
        CursoCapacitacion::factory()->create(['nombre' => 'Zetatécnica']);
        CursoCapacitacion::factory()->create(['nombre' => 'Alfabetización digital']);

        $nombres = $this->service()->todos()->pluck('nombre')->all();

        $this->assertSame(['Alfabetización digital', 'Zetatécnica'], $nombres);
    }
}
