<?php

namespace Tests\Feature\AsistenciaGeneral;

use App\Models\User;
use App\Modules\AsistenciaGeneral\Services\AsistenciaGeneralService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\RolEnum;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsistenciaGeneralServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): AsistenciaGeneralService
    {
        return $this->app->make(AsistenciaGeneralService::class);
    }

    public function test_buscar_persona_encuentra_en_las_tres_fuentes_por_nombre_o_dni(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Zoraida', 'apellidos' => 'Quispe', 'dni' => '11111111']);
        $docente = User::factory()->create(['name' => 'Mario Docente', 'dni' => '22222222']);
        $docente->assignRole(RolEnum::DOCENTE->value);
        $personal = Personal::factory()->create(['nombres' => 'Walter', 'apellidos' => 'Portero', 'dni' => '33333333']);

        $resultados = $this->service()->buscarPersona('Zoraida');
        $this->assertCount(1, $resultados);
        $this->assertSame($estudiante->id, $resultados->first()['persona']->id);

        $resultados = $this->service()->buscarPersona('22222222');
        $this->assertCount(1, $resultados);
        $this->assertSame($docente->id, $resultados->first()['persona']->id);

        $resultados = $this->service()->buscarPersona('Portero');
        $this->assertCount(1, $resultados);
        $this->assertSame($personal->id, $resultados->first()['persona']->id);
    }

    public function test_buscar_persona_excluye_estudiantes_y_apoderados_de_los_resultados_de_user(): void
    {
        $usuarioEstudiante = User::factory()->create(['name' => 'Luis Estudiante', 'dni' => '44444444']);
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);

        $resultados = $this->service()->buscarPersona('44444444');

        $this->assertCount(0, $resultados);
    }

    public function test_registrar_ingreso_crea_el_registro_y_rechaza_un_segundo_ingreso_sin_salida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $registrador = User::factory()->create();

        $registro = $this->service()->registrarIngreso($estudiante, TurnoDiaEnum::MANANA, $registrador);

        $this->assertDatabaseHas('asistencias_generales', [
            'id' => $registro->id,
            'persona_type' => Estudiante::class,
            'persona_id' => $estudiante->id,
            'turno' => 'manana',
        ]);
        $this->assertNull($registro->hora_salida);

        $this->expectException(ValidationException::class);
        $this->service()->registrarIngreso($estudiante, TurnoDiaEnum::TARDE, $registrador);
    }

    public function test_registrar_salida_cierra_el_registro_abierto(): void
    {
        $personal = Personal::factory()->create();
        $registrador = User::factory()->create();

        $registro = $this->service()->registrarIngreso($personal, TurnoDiaEnum::TARDE, $registrador);
        $this->assertNotNull($this->service()->registroAbiertoDe($personal));
        $cerrado = $this->service()->registrarSalida($registro);

        $this->assertNotNull($cerrado->hora_salida);
        $this->assertNull($this->service()->registroAbiertoDe($personal));
    }

    public function test_historial_del_dia_solo_trae_los_registros_de_la_fecha_pedida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $registrador = User::factory()->create();
        $this->service()->registrarIngreso($estudiante, TurnoDiaEnum::NOCHE, $registrador);

        $this->assertCount(1, $this->service()->historialDelDia());
        $this->assertCount(0, $this->service()->historialDelDia(now()->subDay()->toDateString()));
    }
}
