<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class NotaFinalAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_docente_dueno_del_horario_puede_editar_la_nota_final(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->assertTrue(Gate::forUser($docente)->allows('evaluaciones.editar-nota-final', $horario));
    }

    public function test_otro_docente_no_puede_editar_la_nota_final(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->assertFalse(Gate::forUser($otroDocente)->allows('evaluaciones.editar-nota-final', $horario));
    }

    public function test_direccion_puede_editar_la_nota_final_de_cualquier_horario(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        $horario = Horario::factory()->create();

        $this->assertTrue(Gate::forUser($direccion)->allows('evaluaciones.editar-nota-final', $horario));
    }

    public function test_un_estudiante_no_puede_editar_la_nota_final(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $estudianteUser = User::factory()->create();
        $estudianteUser->assignRole(RolEnum::ESTUDIANTE->value);
        $horario = Horario::factory()->create();

        $this->assertFalse(Gate::forUser($estudianteUser)->allows('evaluaciones.editar-nota-final', $horario));
    }
}
