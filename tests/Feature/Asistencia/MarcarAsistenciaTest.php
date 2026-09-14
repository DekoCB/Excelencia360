<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MarcarAsistenciaTest extends TestCase
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
    private function estudianteConClaseUnDomingo(): array
    {
        $domingo = Carbon::now()->next(Carbon::SUNDAY);

        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => $domingo->copy()->subMonth()->format('Y-m-d'),
            'fecha_fin' => $domingo->copy()->addMonth()->format('Y-m-d'),
        ]);
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $horario->dias()->delete();
        $horario->dias()->create([
            'dia_semana' => DiaSemanaEnum::DOMINGO,
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
        ]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id, 'dni' => '87654321']);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        return [$horario, $usuario, $estudiante, $domingo];
    }

    public function test_el_estudiante_marca_su_asistencia_confirmando_su_dni(): void
    {
        [$horario, $usuario, $estudiante, $domingo] = $this->estudianteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($usuario);

        Volt::test('asistencia.marcar')
            ->set('dni', $estudiante->dni)
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $domingo->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
    }

    public function test_rechaza_un_dni_que_no_coincide_con_el_del_estudiante(): void
    {
        [, $usuario, , $domingo] = $this->estudianteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));
        $this->actingAs($usuario);

        Volt::test('asistencia.marcar')
            ->set('dni', '00000000')
            ->call('confirmar')
            ->assertHasErrors('dni');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_no_deja_marcar_asistencia_fuera_del_horario_de_clase(): void
    {
        [, $usuario, $estudiante, $domingo] = $this->estudianteConClaseUnDomingo();

        $this->travelTo($domingo->copy()->setTime(10, 0));
        $this->actingAs($usuario);

        Volt::test('asistencia.marcar')
            ->set('dni', $estudiante->dni)
            ->call('confirmar')
            ->assertHasErrors('dni');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_un_docente_no_puede_acceder_a_la_pagina_de_autorregistro(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('asistencia.marcar'))
            ->assertForbidden();
    }
}
