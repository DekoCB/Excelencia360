<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CertificadoCapacitacionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function coordinador(): User
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        return $coordinador;
    }

    public function test_coordinador_emite_un_certificado_de_capacitacion_desde_el_panel(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->capacitacion()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('estudianteSeleccionadoNombre', $estudiante->nombreCompleto())
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => 'certificado_capacitacion',
            'curso_id' => $curso->id,
            'numero_registro' => '3002324002',
        ]);
    }

    public function test_no_permite_emitir_un_certificado_de_capacitacion_sin_curso(): void
    {
        $estudiante = Estudiante::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasErrors(['cursoId']);

        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_no_permite_emitir_un_certificado_de_capacitacion_sin_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->capacitacion()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudiante->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoId', (string) $curso->id)
            ->call('emitir')
            ->assertHasErrors(['numeroRegistro']);

        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_no_permite_repetir_un_numero_de_registro_ya_usado(): void
    {
        $curso = Curso::factory()->capacitacion()->create();
        $estudianteUno = Estudiante::factory()->create();
        $estudianteDos = Estudiante::factory()->create();

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudianteUno->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasNoErrors();

        Volt::test('certificados.index')
            ->set('estudianteSeleccionadoId', $estudianteDos->id)
            ->set('tipoDocumentoEmitir', TipoDocumentoEnum::CERTIFICADO_CAPACITACION->value)
            ->set('cursoId', (string) $curso->id)
            ->set('numeroRegistro', '3002324002')
            ->call('emitir')
            ->assertHasErrors(['numeroRegistro']);

        $this->assertDatabaseCount('certificados', 1);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     */
    private function archivoExcel(array $encabezados, array $filas): UploadedFile
    {
        $hoja = new Spreadsheet;
        $hoja->getActiveSheet()->fromArray($encabezados, null, 'A1');
        $hoja->getActiveSheet()->fromArray($filas, null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'capacitacion_test_').'.xlsx';
        (new Xlsx($hoja))->save($ruta);

        $archivo = UploadedFile::fake()->createWithContent('capacitacion.xlsx', file_get_contents($ruta));
        unlink($ruta);

        return $archivo;
    }

    public function test_coordinador_importa_certificados_de_capacitacion_desde_un_archivo(): void
    {
        Storage::fake('local');

        $estudiante = Estudiante::factory()->create(['dni' => '72552221']);

        $archivo = $this->archivoExcel(
            ['DNI', 'Nombres', 'Apellidos', 'Numero de Registro', 'Nombre del Curso', 'Horas Lectivas', 'Documento de Autorizacion'],
            [['72552221', 'Ademir Erikson', 'Portillo Livisi', '3002324002', 'Ofimática Nivel Avanzado', '130', 'R.D.R. N°2182-2023-DREP']],
        );

        $this->actingAs($this->coordinador());

        Volt::test('certificados.index')
            ->set('archivoCapacitacion', $archivo)
            ->call('importarCapacitacionDesdeExcel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificados', [
            'estudiante_id' => $estudiante->id,
            'tipo' => 'certificado_capacitacion',
            'numero_registro' => '3002324002',
        ]);
        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Ofimática Nivel Avanzado',
            'tipo' => 'capacitacion',
            'horas' => 130,
        ]);
    }
}
