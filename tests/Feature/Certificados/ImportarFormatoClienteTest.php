<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ImportarFormatoClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear un estudiante nuevo asigna el rol Estudiante a su cuenta de
        // acceso (ver MatriculaService::registrarEstudiante()), así que el
        // rol debe existir de antemano.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): CertificadoService
    {
        return app(CertificadoService::class);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return Collection<int, Collection<string, mixed>>
     */
    private function filas(array $filas): Collection
    {
        return collect($filas)->map(fn (array $fila) => collect($fila));
    }

    public function test_previsualizar_separa_nombres_y_apellidos_con_la_regla_estandar(): void
    {
        $preview = $this->service()->previsualizarImportacionCapacitacionFormatoCliente($this->filas([
            [
                'dni' => '72276899',
                'num_registro' => 'GE-2026-004/001',
                'nombres_y_apellidos' => 'MARIA ROSA PISCOYA INCHAUSTEGUI',
                'curso' => 'Psicología Educativa',
                'horas' => '128',
                'documento' => 'R.G.G. N° 004-2026-GE360',
                'nota' => '17',
            ],
        ]));

        $this->assertCount(1, $preview);
        $this->assertSame('MARIA ROSA', $preview[0]['nombres']);
        $this->assertSame('PISCOYA INCHAUSTEGUI', $preview[0]['apellidos']);
        $this->assertSame('72276899', $preview[0]['dni']);
        $this->assertSame(17.0, $preview[0]['nota']);
        $this->assertFalse($preview[0]['estudiante_existe']);
    }

    public function test_previsualizar_rellena_el_dni_con_ceros_a_la_izquierda(): void
    {
        $preview = $this->service()->previsualizarImportacionCapacitacionFormatoCliente($this->filas([
            [
                'dni' => '1221300',
                'num_registro' => 'GE-2026-004/012',
                'nombres_y_apellidos' => 'PEDRO PAUCAR HUANCA',
                'curso' => 'Curso',
                'horas' => '128',
            ],
        ]));

        $this->assertSame('01221300', $preview[0]['dni']);
    }

    public function test_previsualizar_marca_si_el_estudiante_ya_existe(): void
    {
        Estudiante::factory()->create(['dni' => '72276899']);

        $preview = $this->service()->previsualizarImportacionCapacitacionFormatoCliente($this->filas([
            [
                'dni' => '72276899',
                'num_registro' => 'GE-2026-004/001',
                'nombres_y_apellidos' => 'MARIA ROSA PISCOYA INCHAUSTEGUI',
                'curso' => 'Curso',
                'horas' => '128',
            ],
            [
                'dni' => '99999999',
                'num_registro' => 'GE-2026-004/002',
                'nombres_y_apellidos' => 'ALGUIEN NUEVO AQUI',
                'curso' => 'Curso',
                'horas' => '128',
            ],
        ]));

        $this->assertTrue($preview[0]['estudiante_existe']);
        $this->assertFalse($preview[1]['estudiante_existe']);
    }

    public function test_previsualizar_omite_filas_sin_dni(): void
    {
        $preview = $this->service()->previsualizarImportacionCapacitacionFormatoCliente($this->filas([
            [
                'dni' => '',
                'num_registro' => 'GE-2026-004/089',
                'nombres_y_apellidos' => '',
                'curso' => 'Curso',
                'horas' => '128',
            ],
        ]));

        $this->assertCount(0, $preview);
    }

    public function test_confirmar_crea_al_estudiante_nuevo_y_su_certificado(): void
    {
        $emisor = User::factory()->create();

        $resultado = $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2,
                'dni' => '72276899',
                'nombres' => 'Maria Rosa',
                'apellidos' => 'Piscoya Inchaustegui',
                'numero_registro' => 'GE-2026-004/001',
                'curso' => 'Psicología Educativa',
                'horas_lectivas' => 128,
                'documento_autorizacion' => 'R.G.G. N° 004-2026-GE360',
                'nota' => 17.0,
            ],
        ], $emisor);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);
        $this->assertDatabaseHas('estudiantes', ['dni' => '72276899', 'nombres' => 'Maria Rosa', 'fecha_nacimiento' => null]);
        $this->assertDatabaseHas('certificados', ['numero_registro' => 'GE-2026-004/001', 'nota' => '17.00']);
    }

    public function test_confirmar_reutiliza_un_estudiante_existente_sin_pisar_su_nombre(): void
    {
        $emisor = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['dni' => '72276899', 'nombres' => 'Nombre Original']);

        $resultado = $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2,
                'dni' => '72276899',
                'nombres' => 'Nombre Distinto Que Trajo El Excel',
                'apellidos' => 'Apellido Distinto',
                'numero_registro' => 'GE-2026-004/001',
                'curso' => 'Curso',
                'horas_lectivas' => 128,
                'documento_autorizacion' => null,
                'nota' => null,
            ],
        ], $emisor);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertDatabaseCount('estudiantes', 1);
        $this->assertDatabaseHas('estudiantes', ['id' => $estudiante->id, 'nombres' => 'Nombre Original']);
        $this->assertDatabaseHas('certificados', ['estudiante_id' => $estudiante->id, 'numero_registro' => 'GE-2026-004/001']);
    }

    public function test_confirmar_con_dni_repetido_en_el_mismo_lote_crea_un_solo_estudiante_y_dos_certificados(): void
    {
        $emisor = User::factory()->create();

        $resultado = $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2, 'dni' => '72276899', 'nombres' => 'Maria', 'apellidos' => 'Piscoya',
                'numero_registro' => 'GE-2026-004/001', 'curso' => 'Curso', 'horas_lectivas' => 128,
                'documento_autorizacion' => null, 'nota' => null,
            ],
            [
                'fila' => 3, 'dni' => '72276899', 'nombres' => 'Maria', 'apellidos' => 'Piscoya',
                'numero_registro' => 'GE-2026-004/040', 'curso' => 'Curso', 'horas_lectivas' => 128,
                'documento_autorizacion' => null, 'nota' => null,
            ],
        ], $emisor);

        $this->assertSame(2, $resultado['exitosos']);
        $this->assertDatabaseCount('estudiantes', 1);
        $this->assertDatabaseCount('certificados', 2);
    }

    public function test_confirmar_reutiliza_el_curso_si_ya_existe(): void
    {
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create(['nombre' => 'Curso Ya Existente', 'horas_lectivas' => 100]);

        $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2, 'dni' => '72276899', 'nombres' => 'Maria', 'apellidos' => 'Piscoya',
                'numero_registro' => 'GE-2026-004/001', 'curso' => 'Curso Ya Existente', 'horas_lectivas' => 999,
                'documento_autorizacion' => null, 'nota' => null,
            ],
        ], $emisor);

        $this->assertDatabaseCount('cursos_capacitacion', 1);
        $this->assertDatabaseHas('certificados', ['curso_capacitacion_id' => $curso->id]);
        $this->assertDatabaseHas('cursos_capacitacion', ['id' => $curso->id, 'horas_lectivas' => 100]);
    }

    public function test_confirmar_una_fila_de_estudiante_nuevo_sin_nombre_se_reporta_como_error(): void
    {
        $emisor = User::factory()->create();

        $resultado = $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2, 'dni' => '72276899', 'nombres' => '', 'apellidos' => '',
                'numero_registro' => 'GE-2026-004/001', 'curso' => 'Curso', 'horas_lectivas' => 128,
                'documento_autorizacion' => null, 'nota' => null,
            ],
        ], $emisor);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_confirmar_un_numero_de_registro_repetido_se_reporta_como_error_sin_afectar_las_demas(): void
    {
        $emisor = User::factory()->create();
        Certificado::factory()->create(['numero_registro' => 'GE-2026-004/001']);

        $resultado = $this->service()->confirmarImportacionCapacitacionFormatoCliente([
            [
                'fila' => 2, 'dni' => '72276899', 'nombres' => 'Maria', 'apellidos' => 'Piscoya',
                'numero_registro' => 'GE-2026-004/001', 'curso' => 'Curso', 'horas_lectivas' => 128,
                'documento_autorizacion' => null, 'nota' => null,
            ],
            [
                'fila' => 3, 'dni' => '44537334', 'nombres' => 'Fredy', 'apellidos' => 'Cuito',
                'numero_registro' => 'GE-2026-004/002', 'curso' => 'Curso', 'horas_lectivas' => 128,
                'documento_autorizacion' => null, 'nota' => null,
            ],
        ], $emisor);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
    }
}
