<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array{0: Horario, 1: User, 2: Estudiante, 3: Carbon}
     */
    private function docenteConClaseUnDomingo(): array
    {
        $domingo = Carbon::now()->next(Carbon::SUNDAY);

        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => $domingo->copy()->subMonth()->format('Y-m-d'),
            'fecha_fin' => $domingo->copy()->addMonth()->format('Y-m-d'),
        ]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'docente_id' => $docente->id]);
        $horario->dias()->delete();
        $horario->dias()->create([
            'dia_semana' => DiaSemanaEnum::DOMINGO,
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
        ]);

        $usuarioEstudiante = User::factory()->create();
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        return [$horario, $docente, $estudiante, $domingo];
    }

    public function test_el_docente_escanea_el_qr_de_un_estudiante_matriculado_y_lo_marca_presente(): void
    {
        [$horario, $docente, $estudiante, $domingo] = $this->docenteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('escanearQr', $estudiante->obtenerOCrearQrToken())
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $domingo->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
    }

    public function test_escanear_un_codigo_no_reconocido_no_crea_registro(): void
    {
        [$horario, $docente, , $domingo] = $this->docenteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('escanearQr', 'codigo-inventado-que-no-existe')
            ->assertSee('Código QR no reconocido.');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_escanear_el_qr_de_un_estudiante_no_matriculado_en_el_horario_no_crea_registro(): void
    {
        [$horario, $docente, , $domingo] = $this->docenteConClaseUnDomingo();
        $otroEstudiante = Estudiante::factory()->create();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('escanearQr', $otroEstudiante->obtenerOCrearQrToken())
            ->assertSee('no está matriculado');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_escanear_no_pisa_un_registro_que_el_docente_ya_marco_a_mano(): void
    {
        [$horario, $docente, $estudiante, $domingo] = $this->docenteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));

        Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $domingo->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('escanearQr', $estudiante->obtenerOCrearQrToken());

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);
        $this->assertDatabaseCount('asistencias', 1);
    }

    public function test_no_se_puede_escanear_si_la_fecha_seleccionada_no_es_hoy(): void
    {
        [$horario, $docente, $estudiante, $domingo] = $this->docenteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->set('fecha', $domingo->copy()->subWeek()->format('Y-m-d'))
            ->call('escanearQr', $estudiante->obtenerOCrearQrToken())
            ->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_un_coordinador_que_solo_supervisa_no_puede_escanear(): void
    {
        [$horario, , $estudiante, $domingo] = $this->docenteConClaseUnDomingo();

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($coordinador);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('escanearQr', $estudiante->obtenerOCrearQrToken())
            ->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
    }
}
