<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ImportarCapacitacionDesdeFilasTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_importa_un_certificado_creando_el_curso_si_no_existe(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '72552221']);
        $emisor = User::factory()->create();

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '72552221',
                'nombres' => 'Ademir Erikson',
                'apellidos' => 'Portillo Livisi',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
                'documento_de_autorizacion' => 'R.D.R. N°2182-2023-DREP',
            ],
        ]), $emisor);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(0, $resultado['errores']);

        $this->assertDatabaseHas('cursos_capacitacion', [
            'nombre' => 'Ofimática Nivel Avanzado',
            'horas_lectivas' => 130,
            'documento_autorizacion' => 'R.D.R. N°2182-2023-DREP',
        ]);
        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => 'certificado_capacitacion',
            'numero_registro' => '3002324002',
        ]);
    }

    public function test_reutiliza_un_curso_existente_sin_sobrescribir_sus_datos(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '72552221']);
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create([
            'nombre' => 'Ofimática Nivel Avanzado',
            'horas_lectivas' => 130,
            'documento_autorizacion' => 'R.D.R. N°2182-2023-DREP',
        ]);

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '72552221',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '999',
                'documento_de_autorizacion' => 'OTRO DOCUMENTO',
            ],
        ]), $emisor);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertDatabaseCount('cursos_capacitacion', 1);
        $this->assertDatabaseHas('certificados', [
            'curso_capacitacion_id' => $curso->id,
            'numero_registro' => '3002324002',
        ]);
        // El curso ya existía: no se sobrescriben sus horas/documento con
        // lo que traía la fila.
        $this->assertDatabaseHas('cursos_capacitacion', ['id' => $curso->id, 'horas_lectivas' => 130]);
    }

    public function test_una_fila_con_dni_inexistente_se_reporta_como_error(): void
    {
        $emisor = User::factory()->create();

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '00000000',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
            ],
        ]), $emisor);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_un_numero_de_registro_ya_usado_se_reporta_como_error(): void
    {
        $estudianteUno = Estudiante::factory()->create(['dni' => '11111111']);
        $estudianteDos = Estudiante::factory()->create(['dni' => '22222222']);
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '11111111',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => $curso->nombre,
                'horas_lectivas' => (string) $curso->horas_lectivas,
            ],
        ]), $emisor);

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '22222222',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => $curso->nombre,
                'horas_lectivas' => (string) $curso->horas_lectivas,
            ],
        ]), $emisor);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertDatabaseCount('certificados', 1);
        $this->assertDatabaseHas('certificados', ['estudiante_id' => $estudianteUno->id]);
        $this->assertDatabaseMissing('certificados', ['estudiante_id' => $estudianteDos->id]);
    }

    public function test_un_curso_nuevo_sin_horas_lectivas_se_reporta_como_error(): void
    {
        Estudiante::factory()->create(['dni' => '72552221']);
        $emisor = User::factory()->create();

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '72552221',
                'numero_de_registro' => '3002324002',
                'nombre_del_curso' => 'Curso que no existe todavía',
            ],
        ]), $emisor);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertDatabaseCount('cursos_capacitacion', 0);
    }

    public function test_una_fila_sin_numero_de_registro_se_reporta_como_error(): void
    {
        Estudiante::factory()->create(['dni' => '72552221']);
        $emisor = User::factory()->create();

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '72552221',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
            ],
        ]), $emisor);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_procesa_varias_filas_de_forma_independiente(): void
    {
        Estudiante::factory()->create(['dni' => '11111111']);
        Estudiante::factory()->create(['dni' => '22222222']);
        $emisor = User::factory()->create();

        $resultado = $this->service()->emitirCapacitacionDesdeFilas($this->filas([
            [
                'dni' => '11111111',
                'numero_de_registro' => '1000000001',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
            ],
            [
                'dni' => '99999999',
                'numero_de_registro' => '1000000002',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
            ],
            [
                'dni' => '22222222',
                'numero_de_registro' => '1000000003',
                'nombre_del_curso' => 'Ofimática Nivel Avanzado',
                'horas_lectivas' => '130',
            ],
        ]), $emisor);

        $this->assertSame(2, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(3, $resultado['errores'][0]['fila']);
        $this->assertDatabaseCount('certificados', 2);
        // Un solo curso creado, reutilizado por las dos filas válidas.
        $this->assertDatabaseCount('cursos_capacitacion', 1);
    }
}
