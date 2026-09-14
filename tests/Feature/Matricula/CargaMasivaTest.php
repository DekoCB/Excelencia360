<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
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

class CargaMasivaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
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

        $ruta = tempnam(sys_get_temp_dir(), 'carga_masiva_test_').'.xlsx';
        (new Xlsx($hoja))->save($ruta);

        // Livewire::set() en tests espera el wrapper Testing\File que crea
        // UploadedFile::fake() (expone una propiedad ->name que un
        // UploadedFile real no tiene) -- por eso se envuelve el contenido
        // real del xlsx generado en vez de instanciar UploadedFile directo.
        $archivo = UploadedFile::fake()->createWithContent('archivo.xlsx', file_get_contents($ruta));
        unlink($ruta);

        return $archivo;
    }

    public function test_coordinador_carga_estudiantes_desde_un_excel_real(): void
    {
        Storage::fake('local');
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $archivo = $this->archivoExcel(
            ['nombres', 'apellidos', 'dni', 'fecha_nacimiento'],
            [['Mario', 'Rojas Díaz', '65432109', '10/05/1988']]
        );

        $this->actingAs($coordinador);

        Volt::test('matricula.carga-masiva-estudiantes')
            ->set('archivo', $archivo)
            ->call('procesar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estudiantes', ['dni' => '65432109']);
    }

    public function test_un_docente_no_puede_acceder_a_la_carga_masiva_de_estudiantes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('matricula.carga-masiva-estudiantes'))
            ->assertForbidden();
    }

    public function test_coordinador_matricula_masivamente_desde_un_excel_real(): void
    {
        Storage::fake('local');
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);
        Grado::factory()->create(['nombre' => '3ro de Secundaria']);
        $estudiante = Estudiante::factory()->create(['dni' => '22334455', 'es_menor_edad' => false]);

        $archivo = $this->archivoExcel(['dni', 'grado'], [['22334455', '3ro de Secundaria']]);

        $this->actingAs($coordinador);

        Volt::test('matricula.carga-masiva')
            ->set('cicloId', (string) $ciclo->id)
            ->set('archivo', $archivo)
            ->call('procesar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
    }

    public function test_un_docente_no_puede_acceder_a_la_matricula_masiva(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('matricula.carga-masiva'))
            ->assertForbidden();
    }
}
