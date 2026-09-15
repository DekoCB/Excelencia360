<?php

namespace Tests\Feature\Busqueda;

use App\Models\User;
use App\Modules\Busqueda\Services\BusquedaGlobalService;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Personal\Models\Personal;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusquedaGlobalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): BusquedaGlobalService
    {
        return $this->app->make(BusquedaGlobalService::class);
    }

    private function coordinador(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        return $usuario;
    }

    public function test_termino_muy_corto_no_devuelve_nada(): void
    {
        Estudiante::factory()->create(['nombres' => 'A']);

        $resultados = $this->service()->buscar($this->coordinador(), 'a');

        $this->assertCount(0, $resultados);
    }

    public function test_busca_estudiante_por_nombre_apellido_o_dni(): void
    {
        $estudiante = Estudiante::factory()->create([
            'nombres' => 'Rosa Elvira',
            'apellidos' => 'Ccama Turpo',
            'dni' => '74123698',
        ]);

        $service = $this->service();
        $coordinador = $this->coordinador();

        $this->assertTrue($service->buscar($coordinador, 'Rosa Elvira')->contains(fn ($r) => str_contains($r->titulo, 'Rosa Elvira')));
        $this->assertTrue($service->buscar($coordinador, 'Ccama')->contains(fn ($r) => str_contains($r->titulo, 'Ccama')));

        $porDni = $service->buscar($coordinador, '74123698');
        $this->assertCount(1, $porDni);
        $this->assertSame('Estudiante', $porDni->first()->tipo);
        $this->assertSame(route('matricula.show', $estudiante->id), $porDni->first()->url);
    }

    public function test_busca_apoderado_y_enlaza_a_la_ficha_de_su_hijo(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Hijo', 'apellidos' => 'De Prueba']);
        Apoderado::factory()->create(['estudiante_id' => $estudiante->id, 'nombres' => 'Marleny Apaza Huanca']);

        $resultados = $this->service()->buscar($this->coordinador(), 'Marleny Apaza');

        $this->assertCount(1, $resultados);
        $this->assertSame('Apoderado', $resultados->first()->tipo);
        $this->assertSame(route('matricula.show', $estudiante->id), $resultados->first()->url);
        $this->assertStringContainsString('Hijo De Prueba', $resultados->first()->subtitulo);
    }

    public function test_busca_docente_por_nombre_de_usuario_y_enlaza_al_indice_con_su_dni(): void
    {
        $docenteUser = User::factory()->create(['name' => 'Edith Choque Flores', 'dni' => '65478932']);
        Docente::factory()->create(['user_id' => $docenteUser->id]);

        $resultados = $this->service()->buscar($this->coordinador(), 'Edith Choque');

        $this->assertCount(1, $resultados);
        $this->assertSame('Docente', $resultados->first()->tipo);
        $this->assertSame(route('docentes.index', ['q' => '65478932']), $resultados->first()->url);
    }

    public function test_busca_personal_por_nombre_apellido_o_dni(): void
    {
        Personal::factory()->create(['nombres' => 'Juana', 'apellidos' => 'Flores Mamani', 'dni' => '11223344']);

        $resultados = $this->service()->buscar($this->coordinador(), 'Flores Mamani');

        $this->assertCount(1, $resultados);
        $this->assertSame('Personal', $resultados->first()->tipo);
        $this->assertSame(route('personal.index', ['q' => '11223344']), $resultados->first()->url);
    }

    public function test_un_usuario_sin_permiso_matricula_ver_no_ve_estudiantes_ni_apoderados(): void
    {
        Estudiante::factory()->create(['nombres' => 'Visible Solo Para Staff']);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $resultados = $this->service()->buscar($docente, 'Visible Solo');

        $this->assertCount(0, $resultados);
    }

    public function test_un_usuario_sin_permiso_docentes_ver_no_ve_docentes(): void
    {
        $docenteUser = User::factory()->create(['name' => 'Docente Oculto']);
        Docente::factory()->create(['user_id' => $docenteUser->id]);

        $estudianteUser = User::factory()->create();
        $estudianteUser->assignRole(RolEnum::ESTUDIANTE->value);

        $resultados = $this->service()->buscar($estudianteUser, 'Docente Oculto');

        $this->assertCount(0, $resultados);
    }

    public function test_un_usuario_sin_permiso_personal_ver_no_ve_personal(): void
    {
        Personal::factory()->create(['nombres' => 'Oculto', 'apellidos' => 'Para Docentes']);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $resultados = $this->service()->buscar($docente, 'Oculto Para');

        $this->assertCount(0, $resultados);
    }

    public function test_resultados_se_limitan_a_5_por_tipo(): void
    {
        Estudiante::factory()->count(8)->create(['nombres' => 'Repetido']);

        $resultados = $this->service()->buscar($this->coordinador(), 'Repetido');

        $this->assertCount(5, $resultados);
    }
}
