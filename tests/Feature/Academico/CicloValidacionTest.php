<?php

namespace Tests\Feature\Academico;

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CicloValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CicloService
    {
        return $this->app->make(CicloService::class);
    }

    public function test_un_ciclo_grupo_1_debe_iniciar_en_enero(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-08-30',
        ]);
    }

    public function test_un_ciclo_debe_durar_los_6_meses_declarados_por_su_tipo(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026 (corto)',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-02-01',
        ]);
    }

    public function test_un_ciclo_con_fechas_coherentes_se_crea_sin_problema(): void
    {
        $ciclo = $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id, 'nombre' => 'Grupo 1 - 2026']);
    }

    public function test_no_permite_dos_ciclos_del_mismo_tipo_con_fechas_cruzadas(): void
    {
        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026 (duplicado)',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);
    }

    public function test_permite_dos_grupos_de_tipo_distinto_con_fechas_que_se_cruzan(): void
    {
        // A diferencia del tipo, las 4 ventanas rotativas SÍ se solapan
        // entre sí a propósito (admisión cada ~2 meses): Grupo 1 y Grupo 2
        // conviven en mayo-junio sin que sea un error de carga.
        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $ciclo = $this->service()->crear([
            'nombre' => 'Grupo 2 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_2,
            'anio' => 2026,
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-10-31',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id]);
    }

    public function test_periodo_de_matricula_no_puede_abrir_con_mas_de_30_dias_de_anticipacion(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crearPeriodoMatricula($ciclo, '2026-05-01', '2026-07-10');
    }

    public function test_periodo_de_matricula_no_puede_cerrar_despues_de_que_termina_el_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crearPeriodoMatricula($ciclo, '2026-06-15', '2027-01-15');
    }

    public function test_periodo_de_matricula_valido_dentro_de_la_ventana_del_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $periodo = $this->service()->crearPeriodoMatricula($ciclo, '2026-06-15', '2026-07-15');

        $this->assertDatabaseHas('periodos_matricula', ['id' => $periodo->id, 'ciclo_id' => $ciclo->id]);
    }

    public function test_los_4_grupos_tienen_su_mes_de_inicio_fijo(): void
    {
        $this->assertSame(1, TipoCicloEnum::GRUPO_1->mesInicioFijo());
        $this->assertSame(5, TipoCicloEnum::GRUPO_2->mesInicioFijo());
        $this->assertSame(7, TipoCicloEnum::GRUPO_3->mesInicioFijo());
        $this->assertSame(11, TipoCicloEnum::GRUPO_4->mesInicioFijo());
    }

    public function test_siguiente_avanza_dos_posiciones_entre_los_4_grupos(): void
    {
        $this->assertSame(TipoCicloEnum::GRUPO_3, TipoCicloEnum::GRUPO_1->siguiente());
        $this->assertSame(TipoCicloEnum::GRUPO_4, TipoCicloEnum::GRUPO_2->siguiente());
        $this->assertSame(TipoCicloEnum::GRUPO_1, TipoCicloEnum::GRUPO_3->siguiente());
        $this->assertSame(TipoCicloEnum::GRUPO_2, TipoCicloEnum::GRUPO_4->siguiente());
    }

    public function test_avanza_al_siguiente_anio_solo_para_grupo_3_y_grupo_4(): void
    {
        $this->assertFalse(TipoCicloEnum::GRUPO_1->avanzaAlSiguienteAnio());
        $this->assertFalse(TipoCicloEnum::GRUPO_2->avanzaAlSiguienteAnio());
        $this->assertTrue(TipoCicloEnum::GRUPO_3->avanzaAlSiguienteAnio());
        $this->assertTrue(TipoCicloEnum::GRUPO_4->avanzaAlSiguienteAnio());
    }

    public function test_siguiente_ciclo_encuentra_la_fila_del_proximo_grupo_si_existe(): void
    {
        $actual = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_1, 'anio' => 2026]);
        $siguiente = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_3, 'anio' => 2026]);

        $this->assertSame($siguiente->id, $this->service()->siguienteCiclo($actual)->id);
    }

    public function test_siguiente_ciclo_de_grupo_4_busca_el_grupo_2_del_anio_que_sigue(): void
    {
        $actual = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_4, 'anio' => 2026]);
        $siguiente = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_2, 'anio' => 2027]);

        $this->assertSame($siguiente->id, $this->service()->siguienteCiclo($actual)->id);
    }

    public function test_siguiente_ciclo_es_null_si_todavia_no_se_ha_creado(): void
    {
        $actual = Ciclo::factory()->create(['tipo' => TipoCicloEnum::GRUPO_1, 'anio' => 2026]);

        $this->assertNull($this->service()->siguienteCiclo($actual));
    }

    public function test_un_ciclo_anual_no_exige_tipo_ni_mes_de_inicio_fijo(): void
    {
        $ciclo = $this->service()->crear([
            'nombre' => 'SIAGIE Anual - 2026',
            'modalidad' => ModalidadCicloEnum::ANUAL,
            'tipo' => null,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-10-31',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id, 'modalidad' => 'anual', 'tipo' => null]);
    }

    public function test_un_ciclo_anual_debe_durar_los_8_meses_declarados(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'SIAGIE Anual - 2026 (corto)',
            'modalidad' => ModalidadCicloEnum::ANUAL,
            'tipo' => null,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-06-01',
        ]);
    }

    public function test_no_permite_dos_ciclos_anuales_con_fechas_cruzadas(): void
    {
        $this->service()->crear([
            'nombre' => 'SIAGIE Anual - 2026',
            'modalidad' => ModalidadCicloEnum::ANUAL,
            'tipo' => null,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-10-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'SIAGIE Anual - 2026 (duplicado)',
            'modalidad' => ModalidadCicloEnum::ANUAL,
            'tipo' => null,
            'anio' => 2026,
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-11-30',
        ]);
    }

    public function test_un_ciclo_anual_puede_solaparse_con_un_grupo_rotativo_sin_problema(): void
    {
        // Son modalidades independientes: un Grupo 1 y un SIAGIE anual con
        // fechas que se cruzan no es un error de carga.
        $this->service()->crear([
            'nombre' => 'Grupo 1 - 2026',
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $ciclo = $this->service()->crear([
            'nombre' => 'SIAGIE Anual - 2026',
            'modalidad' => ModalidadCicloEnum::ANUAL,
            'tipo' => null,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-10-31',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id]);
    }

    public function test_siguiente_ciclo_es_null_para_un_ciclo_anual(): void
    {
        $actual = Ciclo::factory()->anual()->create();

        $this->assertNull($this->service()->siguienteCiclo($actual));
    }
}
