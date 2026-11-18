<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Un Horario de Instituto ahora puede crearse sin horas definidas (ver
 * HorarioFormTest::test_instituto_puede_crearse_sin_horas_definidas) --
 * mientras eso no se complete, Asistencia debe bloquear el registro con un
 * aviso en vez de la grilla normal. No aplica a curso corto, que ya podía
 * quedar sin horas por diseño.
 */
class AsistenciaSinHorarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function horarioSinHoras(int $docenteId): Horario
    {
        $horario = Horario::factory()->create(['docente_id' => $docenteId]);
        $horario->dias()->update(['hora_inicio' => null, 'hora_fin' => null]);

        return $horario->fresh();
    }

    public function test_el_docente_ve_el_aviso_en_vez_de_la_grilla(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->horarioSinHoras($docente->id);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->assertSee('Este curso no tiene horario asignado')
            ->assertDontSee('Guardar asistencia');
    }

    public function test_guardar_asistencia_sin_horas_definidas_lanza_403(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->horarioSinHoras($docente->id);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_un_horario_con_horas_definidas_no_muestra_el_aviso(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->assertDontSee('Este curso no tiene horario asignado');
    }

    public function test_un_horario_de_curso_corto_sin_horas_no_muestra_el_aviso(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create([
            'docente_id' => $docente->id,
            'ciclo_id' => null,
            'grado_id' => null,
        ]);
        $horario->dias()->update(['hora_inicio' => null, 'hora_fin' => null]);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario->fresh()])
            ->assertDontSee('Este curso no tiene horario asignado');
    }
}
