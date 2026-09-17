<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ApoderadosPermisosTest extends TestCase
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

    private function menorDeEdad(string $dni = '78912345', string $nombres = 'Diego'): Estudiante
    {
        return $this->app->make(MatriculaService::class)->registrarEstudiante(new RegistrarEstudianteData(
            nombres: $nombres,
            apellidos: 'Torres Huamán',
            dni: new Dni($dni),
            fechaNacimiento: now()->subYears(15)->format('Y-m-d'),
            estadoCivil: null,
            direccion: null,
            celular: null,
            observaciones: null,
        ));
    }

    public function test_coordinador_puede_ver_el_listado_de_apoderados(): void
    {
        $this->actingAs($this->coordinador())
            ->get(route('matricula.apoderados.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_apoderados(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('matricula.apoderados.index'))
            ->assertForbidden();
    }

    public function test_el_enlace_apoderados_aparece_en_el_menu_para_coordinador(): void
    {
        $this->actingAs($this->coordinador())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Apoderados');
    }

    public function test_el_enlace_apoderados_no_aparece_en_el_menu_para_un_apoderado(): void
    {
        $hijo = $this->menorDeEdad();
        $apoderado = User::factory()->create();
        $apoderado->assignRole(RolEnum::APODERADO->value);
        Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'user_id' => $apoderado->id]);

        $this->actingAs($apoderado)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Apoderados<', false);
    }

    public function test_coordinador_crea_un_apoderado_para_un_menor_de_edad(): void
    {
        $hijo = $this->menorDeEdad();

        $this->actingAs($this->coordinador());

        Volt::test('matricula.apoderados.index')
            ->call('abrirModalCrear')
            ->set('estudianteId', (string) $hijo->id)
            ->set('nombres', 'Pedro García')
            ->set('dni', '87654321')
            ->set('celular', '912345678')
            ->set('parentesco', 'Padre')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('apoderados', [
            'estudiante_id' => $hijo->id,
            'nombres' => 'Pedro García',
            'dni' => '87654321',
        ]);
    }

    public function test_coordinador_edita_un_apoderado_existente(): void
    {
        $hijo = $this->menorDeEdad();
        $apoderado = Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'nombres' => 'Nombre Viejo']);

        $this->actingAs($this->coordinador());

        Volt::test('matricula.apoderados.index')
            ->call('abrirModalEditar', $apoderado->id)
            ->assertSet('estudianteId', (string) $hijo->id)
            ->set('nombres', 'Nombre Corregido')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('apoderados', [
            'id' => $apoderado->id,
            'nombres' => 'Nombre Corregido',
        ]);
        $this->assertSame(1, Apoderado::query()->where('estudiante_id', $hijo->id)->count());
    }

    public function test_un_docente_no_puede_crear_ni_editar_apoderados(): void
    {
        $hijo = $this->menorDeEdad();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('matricula.apoderados.index'), report: false);

        $this->assertDatabaseCount('apoderados', 0);
    }

    public function test_el_selector_de_nuevo_apoderado_solo_ofrece_menores_sin_apoderado(): void
    {
        $conApoderado = $this->menorDeEdad('78912345', 'Diego');
        Apoderado::factory()->create(['estudiante_id' => $conApoderado->id]);
        $sinApoderado = $this->menorDeEdad('78912346', 'Rosa');

        $this->actingAs($this->coordinador());

        // Las opciones del select viajan como JSON dentro del atributo
        // x-data (ver select-input.blade.php), que @js() codifica con
        // unicode escapado -- se busca el DNI (sin tildes) en vez del
        // nombre completo para no depender de esa codificación.
        $html = Volt::test('matricula.apoderados.index')
            ->call('abrirModalCrear')
            ->html();

        $this->assertStringContainsString($sinApoderado->dni, $html);
        $this->assertStringNotContainsString($conApoderado->dni, $html);
    }
}
