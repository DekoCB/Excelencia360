<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\HorarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HorarioTraslapeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HorarioService
    {
        return $this->app->make(HorarioService::class);
    }

    private function datosBase(): array
    {
        return [
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ];
    }

    public function test_no_permite_dos_horarios_que_se_crucen_en_la_misma_aula(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '19:00:00', 'hora_fin' => '21:00:00'],
            ],
        ]);
    }

    public function test_el_error_de_cruce_de_aula_va_bajo_la_clave_dias_y_sin_segundos(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        try {
            $this->service()->crear([
                ...$base,
                'curso_id' => Curso::factory()->create()->id,
                'docente_id' => User::factory()->create()->id,
                'dias' => [
                    ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '19:00:00', 'hora_fin' => '21:00:00'],
                ],
            ]);
            $this->fail('Se esperaba una ValidationException.');
        } catch (ValidationException $e) {
            $mensaje = $e->errors()['dias'][0];
            $this->assertStringContainsString('18:00–20:00', $mensaje);
            $this->assertStringNotContainsString('18:00:00', $mensaje);
        }
    }

    public function test_no_permite_al_mismo_docente_en_dos_aulas_a_la_misma_hora(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '19:30:00', 'hora_fin' => '21:00:00'],
            ],
        ]);
    }

    public function test_permite_horarios_en_dias_distintos_aunque_se_crucen_en_hora(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $horario = $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::MARTES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::JUEVES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
    }

    public function test_un_horario_con_varios_dias_no_puede_cruzarse_en_solo_uno_de_ellos(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $this->expectException(ValidationException::class);

        // El lunes se cruza con $base (misma aula), aunque el martes esté libre.
        $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::MARTES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);
    }

    public function test_permite_horarios_consecutivos_sin_cruce_real(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $horario = $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '20:00:00', 'hora_fin' => '22:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '20:00:00', 'hora_fin' => '22:00:00'],
            ],
        ]);

        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
    }

    public function test_rechaza_hora_fin_anterior_o_igual_a_hora_inicio(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$this->datosBase(),
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '18:00:00'],
            ],
        ]);
    }

    public function test_rechaza_crear_un_horario_sin_ningun_dia(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$this->datosBase(),
            'dias' => [],
        ]);
    }

    public function test_crear_un_horario_activa_su_aula_virtual_automaticamente(): void
    {
        $horario = $this->service()->crear($this->datosBase());

        $this->assertDatabaseHas('aula_virtual_cursos', ['horario_id' => $horario->id]);
    }

    public function test_actualizar_reemplaza_los_dias_del_horario(): void
    {
        $horario = $this->service()->crear($this->datosBase());

        $actualizado = $this->service()->actualizar($horario, [
            'curso_id' => $horario->curso_id,
            'docente_id' => $horario->docente_id,
            'aula_id' => $horario->aula_id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::VIERNES, 'hora_inicio' => '16:00:00', 'hora_fin' => '18:00:00'],
            ],
        ]);

        $this->assertCount(1, $actualizado->dias);
        $this->assertSame(DiaSemanaEnum::VIERNES, $actualizado->dias->first()->dia_semana);
    }

    public function test_mover_dia_reprograma_solo_esa_fila_conservando_su_hora(): void
    {
        $horario = $this->service()->crear($this->datosBase());
        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);

        $actualizado = $this->service()->moverDia($lunes, DiaSemanaEnum::VIERNES);

        $this->assertCount(2, $actualizado->dias);
        $viernes = $actualizado->dias->firstWhere('dia_semana', DiaSemanaEnum::VIERNES);
        $this->assertNotNull($viernes);
        $this->assertSame('18:00:00', $viernes->hora_inicio);
        $this->assertSame('20:00:00', $viernes->hora_fin);
        $this->assertNull($actualizado->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES));
        $this->assertNotNull($actualizado->dias->firstWhere('dia_semana', DiaSemanaEnum::MIERCOLES));
    }

    public function test_mover_dia_a_un_dia_ocupado_lanza_excepcion_y_no_mueve_nada(): void
    {
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $ocupante = $this->service()->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::VIERNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        $horario = $this->service()->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);
        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);

        $this->expectException(ValidationException::class);
        $this->service()->moverDia($lunes, DiaSemanaEnum::VIERNES);

        $this->assertSame(DiaSemanaEnum::LUNES, $lunes->fresh()->dia_semana);
    }

    public function test_franja_identifica_la_combinacion_lunes_y_miercoles(): void
    {
        $horario = $this->service()->crear($this->datosBase());

        $this->assertSame(FranjaHorarioEnum::LUN_MIE, $horario->franja());
    }

    public function test_franja_es_null_si_los_dias_no_calzan_con_ninguna_franja_institucional(): void
    {
        $horario = $this->service()->crear([
            ...$this->datosBase(),
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::VIERNES, 'hora_inicio' => '16:00:00', 'hora_fin' => '18:00:00'],
            ],
        ]);

        $this->assertNull($horario->franja());
    }
}
