<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\PreguntaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreguntaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PreguntaService
    {
        return $this->app->make(PreguntaService::class);
    }

    public function test_agrega_una_pregunta_de_opcion_unica_con_sus_alternativas(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $pregunta = $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, '¿Capital del Perú?', 4.0, [
            ['texto' => 'Lima', 'es_correcta' => true],
            ['texto' => 'Cusco', 'es_correcta' => false],
        ]);

        $this->assertSame('¿Capital del Perú?', $pregunta->enunciado);
        $this->assertCount(2, $pregunta->alternativas);
        $this->assertSame(1, $pregunta->alternativas()->where('es_correcta', true)->count());
    }

    public function test_agrega_una_pregunta_abierta_sin_alternativas(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $pregunta = $this->service()->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Explica el ciclo del agua.', 6.0);

        $this->assertCount(0, $pregunta->alternativas);
    }

    public function test_rechaza_opcion_unica_con_menos_de_dos_alternativas(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $this->expectException(ValidationException::class);

        $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, 'Pregunta', 4.0, [
            ['texto' => 'Única opción', 'es_correcta' => true],
        ]);
    }

    public function test_rechaza_una_pregunta_de_opcion_sin_ninguna_alternativa_correcta(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $this->expectException(ValidationException::class);

        $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, 'Pregunta', 4.0, [
            ['texto' => 'A', 'es_correcta' => false],
            ['texto' => 'B', 'es_correcta' => false],
        ]);
    }

    public function test_rechaza_opcion_unica_con_mas_de_una_alternativa_correcta(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $this->expectException(ValidationException::class);

        $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, 'Pregunta', 4.0, [
            ['texto' => 'A', 'es_correcta' => true],
            ['texto' => 'B', 'es_correcta' => true],
        ]);
    }

    public function test_opcion_multiple_permite_mas_de_una_alternativa_correcta(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();

        $pregunta = $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_MULTIPLE, 'Pregunta', 4.0, [
            ['texto' => 'A', 'es_correcta' => true],
            ['texto' => 'B', 'es_correcta' => true],
            ['texto' => 'C', 'es_correcta' => false],
        ]);

        $this->assertSame(2, $pregunta->alternativas()->where('es_correcta', true)->count());
    }

    public function test_actualizar_reemplaza_las_alternativas_existentes(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();
        $pregunta = $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, 'Original', 4.0, [
            ['texto' => 'Vieja A', 'es_correcta' => true],
            ['texto' => 'Vieja B', 'es_correcta' => false],
        ]);

        $actualizada = $this->service()->actualizar($pregunta, 'Modificada', 5.0, [
            ['texto' => 'Nueva A', 'es_correcta' => false],
            ['texto' => 'Nueva B', 'es_correcta' => true],
        ]);

        $this->assertSame('Modificada', $actualizada->enunciado);
        $this->assertSame(5.0, (float) $actualizada->puntaje);
        $this->assertCount(2, $actualizada->alternativas);
        $this->assertFalse($actualizada->alternativas->contains('texto', 'Vieja A'));
        $this->assertTrue($actualizada->alternativas->contains('texto', 'Nueva B'));
    }

    public function test_eliminar_borra_la_pregunta_y_sus_alternativas(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();
        $pregunta = $this->service()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, 'Pregunta', 4.0, [
            ['texto' => 'A', 'es_correcta' => true],
            ['texto' => 'B', 'es_correcta' => false],
        ]);

        $this->service()->eliminar($pregunta);

        $this->assertDatabaseMissing('preguntas', ['id' => $pregunta->id]);
        $this->assertDatabaseMissing('alternativas', ['pregunta_id' => $pregunta->id]);
    }
}
