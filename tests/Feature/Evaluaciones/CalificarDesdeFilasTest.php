<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalificarDesdeFilasTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EvaluacionService
    {
        return $this->app->make(EvaluacionService::class);
    }

    private function matricular(Horario $horario, string $dni): Estudiante
    {
        $estudiante = Estudiante::factory()->create(['dni' => $dni]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        return $estudiante;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return Collection<int, Collection<string, mixed>>
     */
    private function filas(array $filas): Collection
    {
        return collect($filas)->map(fn (array $fila) => collect($fila));
    }

    public function test_importa_notas_validas_por_dni(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $estudiante = $this->matricular($horario, '76543210');

        $resultado = $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '76543210', 'nota' => '17.5', 'observaciones' => 'Importado de Google Forms'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 17.5,
            'observaciones' => 'Importado de Google Forms',
        ]);
    }

    public function test_una_fila_con_dni_inexistente_en_el_horario_se_reporta_como_error(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $resultado = $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '00000000', 'nota' => '15'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_una_nota_fuera_de_rango_se_reporta_como_error_y_no_afecta_otras_filas(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $valida = $this->matricular($horario, '11223344');
        $this->matricular($horario, '55667788');

        $resultado = $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '11223344', 'nota' => '19'],
            ['dni' => '55667788', 'nota' => '25'],
        ]), null);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(3, $resultado['errores'][0]['fila']);
        $this->assertDatabaseHas('calificaciones', ['estudiante_id' => $valida->id, 'nota_numerica' => 19.0]);
    }

    public function test_una_nota_no_numerica_se_reporta_como_error(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $this->matricular($horario, '99887766');

        $resultado = $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '99887766', 'nota' => 'sobresaliente'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_una_fila_sin_dni_se_reporta_como_error(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $resultado = $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['nota' => '15'],
        ]), null);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_importar_dos_veces_al_mismo_estudiante_actualiza_en_vez_de_duplicar(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $this->matricular($horario, '33445566');

        $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '33445566', 'nota' => '10'],
        ]), null);
        $service->calificarDesdeFilas($evaluacion, $this->filas([
            ['dni' => '33445566', 'nota' => '16'],
        ]), null);

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseHas('calificaciones', ['nota_numerica' => 16.0]);
    }
}
