<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\IntentoEvaluacionService;
use App\Modules\Evaluaciones\Services\PreguntaService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentoEvaluacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): IntentoEvaluacionService
    {
        return $this->app->make(IntentoEvaluacionService::class);
    }

    private function preguntas(): PreguntaService
    {
        return $this->app->make(PreguntaService::class);
    }

    public function test_puede_rendir_es_falso_si_la_evaluacion_no_esta_publicada(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->create();
        $estudiante = Estudiante::factory()->create();

        $this->assertFalse($this->service()->puedeRendir($evaluacion, $estudiante));
    }

    public function test_puede_rendir_es_falso_si_ya_paso_la_fecha_disponible_hasta(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create([
            'disponible_hasta' => now()->subDay(),
        ]);
        $estudiante = Estudiante::factory()->create();

        $this->assertFalse($this->service()->puedeRendir($evaluacion, $estudiante));
    }

    public function test_puede_rendir_es_falso_si_ya_existe_un_intento(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->enviar($evaluacion, $estudiante, []);

        $this->assertFalse($this->service()->puedeRendir($evaluacion, $estudiante));
    }

    public function test_enviar_autocalifica_opcion_unica_correcta_de_inmediato(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $pregunta = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, '¿2+2?', 20.0, [
            ['texto' => '4', 'es_correcta' => true],
            ['texto' => '5', 'es_correcta' => false],
        ]);
        $correcta = $pregunta->alternativas->firstWhere('es_correcta', true);

        $intento = $this->service()->enviar($evaluacion, $estudiante, [
            $pregunta->id => ['alternativas' => [$correcta->id]],
        ]);

        $this->assertTrue($intento->estaCalificado());
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 20.00,
        ]);
    }

    public function test_enviar_una_alternativa_incorrecta_da_cero_puntos_en_esa_pregunta(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $pregunta = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, '¿2+2?', 20.0, [
            ['texto' => '4', 'es_correcta' => true],
            ['texto' => '5', 'es_correcta' => false],
        ]);
        $incorrecta = $pregunta->alternativas->firstWhere('es_correcta', false);

        $this->service()->enviar($evaluacion, $estudiante, [
            $pregunta->id => ['alternativas' => [$incorrecta->id]],
        ]);

        $this->assertDatabaseHas('calificaciones', ['nota_numerica' => 0.00]);
    }

    public function test_opcion_multiple_solo_da_el_puntaje_completo_si_elige_exactamente_las_correctas(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $pregunta = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::OPCION_MULTIPLE, '¿Números pares?', 20.0, [
            ['texto' => '2', 'es_correcta' => true],
            ['texto' => '3', 'es_correcta' => false],
            ['texto' => '4', 'es_correcta' => true],
        ]);
        $alternativas = $pregunta->alternativas;
        $dos = $alternativas->firstWhere('texto', '2');
        $tres = $alternativas->firstWhere('texto', '3');
        $cuatro = $alternativas->firstWhere('texto', '4');

        // Le falta una correcta y además marca una incorrecta: no es el
        // conjunto exacto, así que la pregunta vale 0 (sin crédito parcial).
        $this->service()->enviar($evaluacion, $estudiante, [
            $pregunta->id => ['alternativas' => [$dos->id, $tres->id]],
        ]);

        $this->assertDatabaseHas('calificaciones', ['nota_numerica' => 0.00]);

        $otroEstudiante = Estudiante::factory()->create();
        $this->service()->enviar($evaluacion, $otroEstudiante, [
            $pregunta->id => ['alternativas' => [$dos->id, $cuatro->id]],
        ]);

        $this->assertDatabaseHas('calificaciones', [
            'estudiante_id' => $otroEstudiante->id,
            'nota_numerica' => 20.00,
        ]);
    }

    public function test_enviar_con_una_pregunta_abierta_no_finaliza_la_calificacion(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $pregunta = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Explica algo.', 20.0);

        $intento = $this->service()->enviar($evaluacion, $estudiante, [
            $pregunta->id => ['texto' => 'Mi respuesta libre.'],
        ]);

        $this->assertFalse($intento->estaCalificado());
        $this->assertDatabaseCount('calificaciones', 0);
        $this->assertDatabaseHas('respuestas_estudiante', [
            'pregunta_id' => $pregunta->id,
            'estudiante_id' => $estudiante->id,
            'texto_respuesta' => 'Mi respuesta libre.',
            'puntaje_obtenido' => null,
        ]);
    }

    public function test_calificar_la_ultima_pregunta_abierta_pendiente_finaliza_la_nota(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudiante = Estudiante::factory()->create();

        $opcionUnica = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, '¿2+2?', 10.0, [
            ['texto' => '4', 'es_correcta' => true],
            ['texto' => '5', 'es_correcta' => false],
        ]);
        $abierta = $this->preguntas()->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Explica algo.', 10.0);
        $correcta = $opcionUnica->alternativas->firstWhere('es_correcta', true);

        $intento = $this->service()->enviar($evaluacion, $estudiante, [
            $opcionUnica->id => ['alternativas' => [$correcta->id]],
            $abierta->id => ['texto' => 'Mi respuesta.'],
        ]);

        $this->assertFalse($intento->estaCalificado());
        $this->assertDatabaseCount('calificaciones', 0);

        $respuestaAbierta = $abierta->respuestas()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->service()->calificarAbierta($respuestaAbierta, 6.0, null);

        // 10 (opción única correcta) + 6 (abierta) = 16 de 20 posibles -> 16.00
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 16.00,
        ]);
        $this->assertTrue($intento->fresh()->estaCalificado());
    }

    public function test_resultados_de_incluye_un_intento_por_estudiante(): void
    {
        $evaluacion = Evaluacion::factory()->virtual()->publicada()->create();
        $estudianteUno = Estudiante::factory()->create();
        $estudianteDos = Estudiante::factory()->create();

        $this->service()->enviar($evaluacion, $estudianteUno, []);

        $resultados = $this->service()->resultadosDe($evaluacion);

        $this->assertTrue($resultados->has($estudianteUno->id));
        $this->assertFalse($resultados->has($estudianteDos->id));
    }
}
