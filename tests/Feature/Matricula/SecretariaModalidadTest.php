<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Las dos secretarías de matrícula (punto 7 del backlog) comparten los
 * mismos permisos matricula.* que Coordinador -- lo que las distingue es el
 * alcance: cada una queda restringida a su propia modalidad vía
 * User::modalidadMatriculaRestringida().
 */
class SecretariaModalidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function estudianteInstituto(): Estudiante
    {
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        return $estudiante;
    }

    private function estudianteCursoCorto(): Estudiante
    {
        $estudiante = Estudiante::factory()->create();
        MatriculaCursoCorto::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => Curso::factory()->capacitacion()->create()->id,
        ]);

        return $estudiante;
    }

    public function test_secretaria_instituto_solo_ve_estudiantes_de_instituto_en_el_listado(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_INSTITUTO->value);

        $estudianteInstituto = $this->estudianteInstituto();
        $estudianteCursoCorto = $this->estudianteCursoCorto();

        $this->actingAs($secretaria);

        Volt::test('matricula.index')
            ->assertSee($estudianteInstituto->nombres)
            ->assertDontSee($estudianteCursoCorto->nombres);
    }

    public function test_secretaria_excelencia_solo_ve_estudiantes_de_curso_corto_en_el_listado(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);

        $estudianteInstituto = $this->estudianteInstituto();
        $estudianteCursoCorto = $this->estudianteCursoCorto();

        $this->actingAs($secretaria);

        Volt::test('matricula.index')
            ->assertSee($estudianteCursoCorto->nombres)
            ->assertDontSee($estudianteInstituto->nombres);
    }

    public function test_coordinador_sigue_viendo_ambas_modalidades_en_el_listado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $estudianteInstituto = $this->estudianteInstituto();
        $estudianteCursoCorto = $this->estudianteCursoCorto();

        $this->actingAs($coordinador);

        Volt::test('matricula.index')
            ->assertSee($estudianteInstituto->nombres)
            ->assertSee($estudianteCursoCorto->nombres);
    }

    public function test_secretaria_instituto_no_puede_abrir_la_ficha_de_un_estudiante_solo_de_curso_corto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_INSTITUTO->value);
        $estudiante = $this->estudianteCursoCorto();

        $this->actingAs($secretaria)
            ->get(route('matricula.show', $estudiante))
            ->assertForbidden();
    }

    public function test_secretaria_excelencia_no_puede_abrir_la_ficha_de_un_estudiante_solo_instituto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);
        $estudiante = $this->estudianteInstituto();

        $this->actingAs($secretaria)
            ->get(route('matricula.show', $estudiante))
            ->assertForbidden();
    }

    public function test_secretaria_instituto_si_puede_abrir_la_ficha_de_su_propia_modalidad(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_INSTITUTO->value);
        $estudiante = $this->estudianteInstituto();

        $this->actingAs($secretaria)
            ->get(route('matricula.show', $estudiante))
            ->assertOk();
    }

    public function test_secretaria_excelencia_no_puede_abrir_el_modal_de_ficha_de_un_estudiante_instituto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);
        $estudiante = $this->estudianteInstituto();

        $this->actingAs($secretaria);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->assertForbidden();
    }

    public function test_el_wizard_oculta_el_selector_de_tipo_y_lo_fija_en_instituto_para_secretaria_instituto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_INSTITUTO->value);

        $this->actingAs($secretaria);

        Volt::test('matricula.wizard')
            ->assertSet('tipoMatricula', 'instituto')
            ->assertDontSee('id="tipoMatricula"');
    }

    public function test_el_wizard_oculta_el_selector_de_tipo_y_lo_fija_en_curso_corto_para_secretaria_excelencia(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);

        $this->actingAs($secretaria);

        Volt::test('matricula.wizard')
            ->assertSet('tipoMatricula', 'curso_corto')
            ->assertDontSee('id="tipoMatricula"');
    }

    public function test_el_wizard_ignora_un_intento_de_forzar_tipo_instituto_desde_secretaria_excelencia(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);

        $this->actingAs($secretaria);

        // Llega hasta el paso 5 (Matrícula) y simula la manipulación directa
        // de wire:model al tipo que el selector (oculto) ya no ofrece.
        Volt::test('matricula.wizard')
            ->set('nombres', 'Rosa')
            ->set('apellidos', 'Medina Luna')
            ->set('dni', '55667988')
            ->set('fechaNacimiento', now()->subYears(25)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 3)
            ->call('avanzar')
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5)
            ->set('tipoMatricula', 'instituto')
            ->call('avanzar')
            ->assertSet('tipoMatricula', 'curso_corto');
    }

    public function test_secretaria_excelencia_no_puede_entrar_a_la_carga_masiva_de_instituto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_EXCELENCIA->value);

        $this->actingAs($secretaria)
            ->get(route('matricula.carga-masiva'))
            ->assertForbidden();
    }

    public function test_secretaria_instituto_si_puede_entrar_a_la_carga_masiva_de_instituto(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole(RolEnum::SECRETARIA_INSTITUTO->value);

        $this->actingAs($secretaria)
            ->get(route('matricula.carga-masiva'))
            ->assertOk();
    }
}
